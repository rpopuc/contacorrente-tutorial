<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use App\Models\Transaction;
use Laravel\Mcp\Server\Tool;
use Illuminate\JsonSchema\JsonSchema;

class ListTransactionsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Lists all transactions based on a filter, ordered by creation date (from newest to oldest).
        Transactions can be filtered by:
        - description
        - fromDate
        - toDate
        - category
        It is possible to set a limit to reduce the number of transactions displayed.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $description = $request->get('description');
        $fromDate = $request->get('fromDate');
        $toDate = $request->get('toDate');
        $category = $request->get('category');
        $limit = $request->get('limit');

        $query = Transaction::query()
            ->where('user_id', $request->user()->id);

        $query->when($description, fn($q) =>
            $q->where('description', 'ilike', "%{$description}%")
        );

        $query->when($fromDate, fn($q) =>
            $q->whereDate('created_at', '>=', $fromDate)
        );

        $query->when($toDate, fn($q) =>
            $q->whereDate('created_at', '<=', $toDate)
        );

        $query->when($category, fn($q) =>
            $q->where('category', $category)
        );

        $query->when($limit, fn($q) =>
            $q->limit($limit)
        );

        $transactions = $query
            ->orderBy('created_at', 'DESC')
            ->get();

        return Response::json($transactions->all());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'fromDate' => $schema->string()
                ->format('date')
                ->description('Initial date for filtering transactions (optional)')
                ->nullable(),

            'toDate' => $schema->string()
                ->format('date')
                ->description('End date for filtering transactions (optional)')
                ->nullable(),

            'category' => $schema->string()
                ->description('Category to filter by (optional). A category is in lowercase and does not contain accents or symbols.')
                ->nullable(),

            'description' => $schema->string()
                ->description('Partial text filter for the description (optional)')
                ->nullable(),

            'limit' => $schema->integer()
                ->description('Maximum number of transactions to be returned. By default, it returns all.')
                ->nullable(),
        ];
    }
}