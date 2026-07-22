export type StatusColor = 'gray' | 'success' | 'warning' | 'danger';

export interface StatusBadgeData {
    value: string;
    label: string;
    color: StatusColor;
    is_finished?: boolean;
}

export interface ImportTotals {
    total: number | null;
    processed: number;
    successful: number;
    failed: number;
}

export interface ImportedProductData {
    id: number;
    row_number: number;
    handle: string | null;
    title: string | null;
    sku: string | null;
    status: StatusBadgeData;
    shopify_product_id: number | null;
    shopify_admin_url: string | null;
    error_message: string | null;
}

export interface ImportRunData {
    id: number;
    original_filename: string;
    status: StatusBadgeData;
    totals: ImportTotals;
    progress: number;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
    products?: ImportedProductData[];
}
