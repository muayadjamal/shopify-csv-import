import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ImportRunData } from '@/types/import';

interface Props {
    runs: ImportRunData[];
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function ImportsIndex({ runs }: Props) {
    return (
        <>
            <Head title="Imports" />

            <div className="flex w-full flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Imports</h1>
                        <p className="text-sm text-muted-foreground">
                            CSV product imports and their status.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/imports/create">
                            <Plus className="size-4" />
                            New import
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent runs</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {runs.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No imports yet. Upload a CSV to get started.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                            <th className="py-2 pr-4 font-medium">
                                                File
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Status
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Progress
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Success / Failed
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Created
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {runs.map((run) => (
                                            <tr
                                                key={run.id}
                                                onClick={() =>
                                                    router.visit(
                                                        `/imports/${run.id}`,
                                                    )
                                                }
                                                className="cursor-pointer border-b border-sidebar-border/40 last:border-0 hover:bg-muted/50"
                                            >
                                                <td className="max-w-[220px] truncate py-3 pr-4 font-medium">
                                                    {run.original_filename}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <StatusBadge
                                                        color={run.status.color}
                                                        label={run.status.label}
                                                    />
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {run.totals.total
                                                        ? `${run.progress}%`
                                                        : '—'}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <span className="text-emerald-600">
                                                        {run.totals.successful}
                                                    </span>
                                                    {' / '}
                                                    <span className="text-red-600">
                                                        {run.totals.failed}
                                                    </span>
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {formatDate(run.created_at)}
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

ImportsIndex.layout = {
    breadcrumbs: [{ title: 'Imports', href: '/imports' }],
};
