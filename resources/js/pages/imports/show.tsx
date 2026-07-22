import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ImportRunData } from '@/types/import';

interface Props {
    run: ImportRunData;
}

const POLL_INTERVAL_MS = 2000;

export default function ShowImport({ run: initialRun }: Props) {
    const [run, setRun] = useState<ImportRunData>(initialRun);
    const flash = usePage().props.flash as
        { success?: string | null } | undefined;
    const flashShown = useRef(false);

    useEffect(() => {
        if (flash?.success && !flashShown.current) {
            flashShown.current = true;
            toast.success(flash.success);
        }
    }, [flash]);

    // Poll the JSON status endpoint until the run reaches a terminal state.
    useEffect(() => {
        if (run.status.is_finished) {
            return;
        }

        const timer = setInterval(async () => {
            const response = await fetch(`/imports/${run.id}/status`, {
                headers: { Accept: 'application/json' },
            });
            const payload = (await response.json()) as { data: ImportRunData };
            setRun(payload.data);
        }, POLL_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [run.id, run.status.is_finished]);

    const products = run.products ?? [];

    return (
        <>
            <Head title={`Import · ${run.original_filename}`} />

            <div className="flex w-full flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="icon">
                        <Link href="/imports">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            {run.original_filename}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Import #{run.id}
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Status</CardTitle>
                        <StatusBadge
                            color={run.status.color}
                            label={run.status.label}
                        />
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Stat
                                label="Total rows"
                                value={run.totals.total ?? '—'}
                            />
                            <Stat
                                label="Processed"
                                value={run.totals.processed}
                            />
                            <Stat
                                label="Successful"
                                value={run.totals.successful}
                                className="text-emerald-600"
                            />
                            <Stat
                                label="Failed"
                                value={run.totals.failed}
                                className="text-red-600"
                            />
                        </div>

                        <div className="flex flex-col gap-1">
                            <div className="flex justify-between text-xs text-muted-foreground">
                                <span>Progress</span>
                                <span>{run.progress}%</span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                <div
                                    className="h-full bg-primary transition-all"
                                    style={{ width: `${run.progress}%` }}
                                />
                            </div>
                        </div>

                        {run.error_message && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                {run.error_message}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rows</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {products.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Waiting for rows to be processed…
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                            <th className="py-2 pr-4 font-medium">
                                                #
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Title
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                SKU
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Status
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Result
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {products.map((product) => (
                                            <tr
                                                key={product.id}
                                                className="border-b border-sidebar-border/40 align-top last:border-0"
                                            >
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {product.row_number}
                                                </td>
                                                <td className="max-w-[220px] truncate py-3 pr-4 font-medium">
                                                    {product.title ?? '—'}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {product.sku ?? '—'}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <StatusBadge
                                                        color={
                                                            product.status.color
                                                        }
                                                        label={
                                                            product.status.label
                                                        }
                                                    />
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {product.shopify_admin_url ? (
                                                        <a
                                                            href={
                                                                product.shopify_admin_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="inline-flex items-center gap-1 text-primary hover:underline"
                                                        >
                                                            View in Shopify
                                                            <ExternalLink className="size-3" />
                                                        </a>
                                                    ) : (
                                                        <span className="text-red-600">
                                                            {product.error_message ??
                                                                '—'}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    className,
}: {
    label: string;
    value: number | string;
    className?: string;
}) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className={`text-2xl font-semibold ${className ?? ''}`}>
                {value}
            </div>
        </div>
    );
}

ShowImport.layout = {
    breadcrumbs: [
        { title: 'Imports', href: '/imports' },
        { title: 'Detail', href: '#' },
    ],
};
