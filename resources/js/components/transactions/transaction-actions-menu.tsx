import { categorize } from '@/actions/App/Http/Controllers/TransactionController';
import exportTransactions from '@/actions/App/Http/Controllers/TransactionExportController';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useIsMobile } from '@/hooks/use-mobile';
import {
    buildQueryString,
    hasActiveFilters,
    serializeFilters,
} from '@/lib/transaction-filter-serialization';
import { reEvaluateTransactionRules } from '@/lib/transaction-re-evaluation';

import { filterTransactionalAccounts, type Account } from '@/types/account';
import {
    type ServerTransaction,
    type TransactionFilters,
} from '@/types/transaction';
import { __ } from '@/utils/i18n';
import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BarChart3,
    ChevronDown,
    Download,
    Tags,
    WandSparkles,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { AddTransactionButton } from './add-transaction-button';
import { TransactionAnalysisDrawer } from './transaction-analysis-drawer';
import { TransferDialog } from './transfer-dialog';

interface TransactionActionsMenuProps {
    transactions: ServerTransaction[];
    /** Whether the user owns an account a manual transaction can be filed in. */
    hasTransactionalAccounts: boolean;
    onReEvaluateComplete?: () => void;
    filters: TransactionFilters;
    /**
     * The user's accounts. Passing them offers a transfer between two of them,
     * which only the main transactions page does.
     */
    accounts?: Account[];
    onTransferCreated?: () => void;
}

/**
 * The bar above the transactions table. Adding a transaction sits here, right
 * above the list it lands in; importing lives in the app header, so it is not
 * repeated here.
 */
export function TransactionActionsMenu({
    transactions,
    hasTransactionalAccounts,
    onReEvaluateComplete,
    filters,
    accounts,
    onTransferCreated,
}: TransactionActionsMenuProps) {
    const isMobile = useIsMobile();
    const [analysisDrawerOpen, setAnalysisDrawerOpen] = useState(false);
    const [analysisHintOpen, setAnalysisHintOpen] = useState(false);
    const [isReEvaluating, setIsReEvaluating] = useState(false);
    const [transferOpen, setTransferOpen] = useState(false);
    const canTransfer = filterTransactionalAccounts(accounts ?? []).length > 1;

    const canAnalyze = hasActiveFilters(filters);

    const handleAnalysisClick = () => {
        if (!canAnalyze) {
            if (isMobile) {
                setAnalysisHintOpen((open) => !open);
            }
            return;
        }
        setAnalysisDrawerOpen(true);
    };

    const handleReEvaluateAll = async () => {
        setIsReEvaluating(true);
        try {
            if ((await reEvaluateTransactionRules()) === 'done') {
                onReEvaluateComplete?.();
            }
        } finally {
            setIsReEvaluating(false);
        }
    };

    // The export takes the filters on screen, so the file holds the rows the
    // table is showing rather than the whole history.
    const exportQuery = buildQueryString({
        ...serializeFilters(filters),
        ...(filters.aiCategorizedOnly && { category_source: 'ai' }),
    });
    const exportUrl = exportQuery
        ? `${exportTransactions.url()}?${exportQuery}`
        : exportTransactions.url();

    const uncategorizedCount = transactions.filter(
        (t) => !t.category_id,
    ).length;

    const hasUncategorized = uncategorizedCount > 0;

    const categorizeLabel = (
        <>
            {__('Categorize')}
            {hasUncategorized && (
                <span className="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                    {uncategorizedCount}
                </span>
            )}
        </>
    );

    const withCategorizeLink = (content: ReactNode) =>
        hasUncategorized ? (
            <Link href={categorize.url()}>{content}</Link>
        ) : (
            content
        );

    return (
        <div className="flex items-center gap-2">
            <AddTransactionButton
                hasTransactionalAccounts={hasTransactionalAccounts}
            />

            <ButtonGroup>
                <TooltipProvider>
                    <Tooltip
                        open={!canAnalyze && analysisHintOpen}
                        onOpenChange={setAnalysisHintOpen}
                    >
                        <TooltipTrigger asChild>
                            <Button
                                variant="outline"
                                className={
                                    !canAnalyze
                                        ? 'cursor-not-allowed opacity-50'
                                        : ''
                                }
                                aria-disabled={!canAnalyze}
                                onClick={handleAnalysisClick}
                            >
                                <BarChart3 className="h-5 w-5" />
                                {__('Analysis')}
                            </Button>
                        </TooltipTrigger>
                        {!canAnalyze && (
                            <TooltipContent>
                                {__('Apply a filter to enable this button')}
                            </TooltipContent>
                        )}
                    </Tooltip>
                </TooltipProvider>

                {!isMobile && (
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    className={
                                        hasUncategorized
                                            ? ''
                                            : 'cursor-not-allowed opacity-50'
                                    }
                                    disabled={!hasUncategorized}
                                    asChild={hasUncategorized}
                                >
                                    {withCategorizeLink(categorizeLabel)}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {hasUncategorized
                                    ? `Categorize ${uncategorizedCount} transactions`
                                    : 'All transactions are categorized'}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                )}

                <DropdownMenu>
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        aria-label={__('More actions')}
                                    >
                                        <ChevronDown className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                            </TooltipTrigger>
                            <TooltipContent>
                                {__('More actions')}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <DropdownMenuContent align="end">
                        {isMobile && (
                            <DropdownMenuItem
                                disabled={!hasUncategorized}
                                asChild={hasUncategorized}
                            >
                                {withCategorizeLink(
                                    <>
                                        <Tags className="mr-2 h-4 w-4" />
                                        {categorizeLabel}
                                    </>,
                                )}
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                            onClick={handleReEvaluateAll}
                            disabled={isReEvaluating}
                        >
                            <WandSparkles className="mr-2 h-4 w-4" />
                            {__('Update categories automatically')}
                        </DropdownMenuItem>
                        {canTransfer && (
                            <DropdownMenuItem
                                // Deferred a frame so the dialog does not race
                                // the menu's own close.
                                onSelect={() =>
                                    requestAnimationFrame(() =>
                                        setTransferOpen(true),
                                    )
                                }
                            >
                                <ArrowLeftRight className="mr-2 h-4 w-4" />
                                {__('Transfer between accounts')}
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem asChild>
                            {/* A plain anchor: this is a file download, not a
                                page Inertia can visit. */}
                            <a href={exportUrl} download>
                                <Download className="mr-2 h-4 w-4" />
                                {canAnalyze
                                    ? __('Export filtered to CSV')
                                    : __('Export to CSV')}
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </ButtonGroup>

            {accounts && (
                <TransferDialog
                    accounts={accounts}
                    open={transferOpen}
                    onOpenChange={setTransferOpen}
                    onCreated={onTransferCreated}
                />
            )}

            <TransactionAnalysisDrawer
                open={analysisDrawerOpen}
                onOpenChange={setAnalysisDrawerOpen}
                filters={filters}
            />
        </div>
    );
}
