<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class TransactionsList extends Component
{
    public array $transactions = [];
    public array $categories = [];
    public int $transactionsCount = 0;
    public float $transactionsSum = 0;

    public ?string $descriptionFilter = null;
    public ?string $categoryFilter = null;
    public ?string $fromDateFilter = null;
    public ?string $toDateFilter = null;

    public function mount(): void
    {
        $this->applyFilter();
    }

    private function listCategories(): void
    {
        $query = Transaction::query()
            ->select('category')
            ->distinct()
            ->where('user_id', Auth::id())
            ->orderBy('category');

        $this->categories = $query
            ->pluck('category')
            ->all();
    }

    private function applyFilter(): void
    {
        $this->listCategories();

        $query = Transaction::query()
            ->where('user_id', Auth::id());

        $query->when($this->descriptionFilter, fn($q) =>
            $q->where('description', 'ilike', "%$this->descriptionFilter%")
        );

        $query->when($this->fromDateFilter, fn($q) =>
            $q->whereDate('created_at', '>=', $this->fromDateFilter)
        );

        $query->when($this->toDateFilter, fn($q) =>
            $q->whereDate('created_at', '<=', $this->toDateFilter)
        );

        $query->when($this->categoryFilter, fn($q) =>
            $q->where('category', $this->categoryFilter)
        );

        $transactions = $query
            ->orderBy('created_at', 'DESC')
            ->get();

        $this->transactionsCount = $transactions->count();
        $this->transactionsSum = $transactions->sum('value');

        $this->transactions = $transactions->all();
    }

    public function updatedDescriptionFilter(): void
    {
        $this->applyFilter();
    }

    public function updatedCategoryFilter(): void
    {
        $this->applyFilter();
    }

    public function updatedFromDateFilter(): void
    {
        $this->applyFilter();
    }

    public function updatedToDateFilter(): void
    {
        $this->applyFilter();
    }

    public function render()
    {
        return view('livewire.transactions-list');
    }
}