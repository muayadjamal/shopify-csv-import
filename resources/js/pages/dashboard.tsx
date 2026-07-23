import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

interface DashboardStats {
    totals: {
        runs: number;
        completed: number;
        processing: number;
        failed: number;
        products_imported: number;
        failed_rows: number;
    };
    ongoing: Array<{
        id: number;
        original_filename: string;
        status: string;
        progress: number;
        processed: number;
        total: number | null;
    }>;
}

const POLL_INTERVAL_MS = 3000;

export default function Dashboard({ stats: initialStats }: { stats: DashboardStats }) {
    const [stats, setStats] = useState<DashboardStats>(initialStats);

    useEffect(() => {
        const timer = setInterval(async () => {
            const response = await fetch('/dashboard/stats', {
                headers: { Accept: 'application/json' },
            });
            setStats((await response.json()) as DashboardStats);
        }, POLL_INTERVAL_MS);

        return () => clearInterval(timer);
    }, []);

    const { totals, ongoing } = stats;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex w-full flex-col gap-6 p-4">
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <StatCard label="Total imports" value={totals.runs} />
                    <StatCard label="Completed" value={totals.completed} accent="text-emerald-600" />
                    <StatCard label="In progress" value={totals.processing} accent="text-amber-600" live={totals.processing > 0} />
                    <StatCard label="Products imported" value={totals.products_imported} accent="text-emerald-600" />
                    <StatCard label="Failed rows" value={totals.failed_rows} accent="text-red-600" />
                    <StatCard label="Failed imports" value={totals.failed} accent="text-red-600" />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Ongoing imports</CardTitle>
                        <span className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                            </span>
                            Live
                        </span>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {ongoing.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No imports are currently running.
                            </p>
                        ) : (
                            ongoing.map((run) => (
                                <Link
                                    key={run.id}
                                    href={`/imports/${run.id}`}
                                    className="flex flex-col gap-1 rounded-lg border border-sidebar-border/70 p-3 hover:bg-muted/50 dark:border-sidebar-border"
                                >
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="truncate font-medium">{run.original_filename}</span>
                                        <span className="text-muted-foreground">
                                            {run.status} · {run.processed}/{run.total ?? '—'}
                                        </span>
                                    </div>
                                    <div className="h-2 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                        <div className="h-full bg-primary transition-all" style={{ width: `${run.progress}%` }} />
                                    </div>
                                </Link>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function StatCard({ label, value, accent, live }: { label: string; value: number; accent?: string; live?: boolean }) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">{label}</span>
                {live && <span className="size-2 animate-pulse rounded-full bg-amber-500" />}
            </div>
            <div className={`mt-2 text-3xl font-semibold ${accent ?? ''}`}>{value}</div>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
