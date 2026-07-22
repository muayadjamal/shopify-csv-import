import { Head, useForm } from '@inertiajs/react';
import { FileUp, UploadCloud, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

const MAX_SIZE_MB = 10;
const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;

function validateFile(file: File): string | null {
    const isCsv =
        file.name.toLowerCase().endsWith('.csv') || file.type === 'text/csv';

    if (!isCsv) {
        return 'The file must be a CSV (.csv).';
    }

    if (file.size > MAX_SIZE_BYTES) {
        return `The file may not be larger than ${MAX_SIZE_MB} MB.`;
    }

    return null;
}

export default function CreateImport() {
    const inputRef = useRef<HTMLInputElement>(null);
    const [clientError, setClientError] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);

    const form = useForm<{ file: File | null }>({ file: null });

    const selectFile = (file: File | undefined) => {
        if (!file) {
            return;
        }

        const error = validateFile(file);
        setClientError(error);
        form.setData('file', error ? null : file);
    };

    const clearFile = () => {
        form.setData('file', null);
        setClientError(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!form.data.file || clientError) {
            return;
        }

        form.post('/imports', {
            forceFormData: true,
            onError: () =>
                toast.error(
                    'Upload failed. Please check the file and try again.',
                ),
        });
    };

    const fileError = clientError ?? form.errors.file;

    return (
        <>
            <Head title="New import" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Import products from CSV</CardTitle>
                            <CardDescription>
                                Upload a Shopify product CSV. Products are
                                imported asynchronously and added to the
                                configured collection.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-4">
                            <label
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setDragging(false);
                                    selectFile(e.dataTransfer.files[0]);
                                }}
                                className={cn(
                                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-10 text-center transition-colors',
                                    dragging
                                        ? 'border-primary bg-primary/5'
                                        : 'border-sidebar-border/70 hover:border-primary/60 dark:border-sidebar-border',
                                    fileError && 'border-red-400',
                                )}
                            >
                                <input
                                    ref={inputRef}
                                    type="file"
                                    accept=".csv,text/csv"
                                    className="sr-only"
                                    onChange={(e) =>
                                        selectFile(e.target.files?.[0])
                                    }
                                    data-testid="csv-input"
                                />
                                <UploadCloud className="size-8 text-muted-foreground" />
                                <span className="font-medium">
                                    Drag &amp; drop your CSV here, or click to
                                    browse
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    CSV up to {MAX_SIZE_MB} MB
                                </span>
                            </label>

                            {form.data.file && (
                                <div className="flex items-center justify-between rounded-lg border border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border">
                                    <span className="flex items-center gap-2 truncate text-sm">
                                        <FileUp className="size-4 shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {form.data.file.name}
                                        </span>
                                    </span>
                                    <button
                                        type="button"
                                        onClick={clearFile}
                                        className="text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="size-4" />
                                    </button>
                                </div>
                            )}

                            {fileError && (
                                <p
                                    className="text-sm text-red-600"
                                    data-testid="file-error"
                                >
                                    {fileError}
                                </p>
                            )}

                            {form.progress && (
                                <div className="h-2 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                    <div
                                        className="h-full bg-primary transition-all"
                                        style={{
                                            width: `${form.progress.percentage ?? 0}%`,
                                        }}
                                    />
                                </div>
                            )}
                        </CardContent>

                        <CardFooter>
                            <Button
                                type="submit"
                                disabled={
                                    !form.data.file ||
                                    !!clientError ||
                                    form.processing
                                }
                            >
                                {form.processing
                                    ? 'Uploading…'
                                    : 'Start import'}
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </div>
        </>
    );
}

CreateImport.layout = {
    breadcrumbs: [
        { title: 'Imports', href: '/imports' },
        { title: 'New import', href: '/imports/create' },
    ],
};
