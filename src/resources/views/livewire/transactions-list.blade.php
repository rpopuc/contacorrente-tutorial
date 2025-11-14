<div class="flex flex-1 flex-col gap-6 h-full">
    <div class="flex w-full">
        <h2 class="text-2xl font-bold uppercase">Dashboard</h2>
    </div>
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
            <input type="text" id="description" wire:model.live.debounce="descriptionFilter" class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
        <div>
            <label for="categoryFilter" class="block text-sm font-medium text-gray-700">Category</label>
            <select id="categoryFilter" wire:model.live="categoryFilter" class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="fromDateFilter" class="block text-sm font-medium text-gray-700">From</label>
            <input type="date" id="fromDateFilter" wire:model.change="fromDateFilter" class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
        <div>
            <label for="toDateFilter" class="block text-sm font-medium text-gray-700">To</label>
            <input type="date" id="toDateFilter" wire:model.change="toDateFilter" class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
    </div>
    <div class="flex gap-6">
        <div class="font-bold">{{ $transactionsCount }} <span class="font-normal text-xs">entradas</span></div>
        <div class="text-xl font-bold">R$ {{ number_format($transactionsSum, 2, ',', '.') }} <span class="font-normal text-xs">total</span></div>
    </div>
    <div class="flex-1 h-full flex flex-col gap-2">
        @foreach ($transactions as $transaction)
            <div class="border rounded-md border-neutral-200 dark:border-neutral-700 p-4 flex justify-between" wire:key="{{ $transaction->id }}">
                <div>
                    <div class="flex gap-4 items-center">
                        <div class="text-xs">{{ $transaction->created_at->format('d/m/Y H:i') }}</div>
                        <div class="text-[.6rem] uppercase bg-gray-700 text-white rounded-md px-2 dark:bg-white dark:text-black">{{ $transaction->category}}</div>
                    </div>
                    <div class="font-bold text-lg">{{ $transaction->description }}</div>
                    <div><span class="text-xs font-bold">R$</span> {{ number_format($transaction->value, 2, ',', '.') }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>