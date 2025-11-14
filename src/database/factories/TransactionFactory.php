<?php

namespace Database\Factories;

use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    private array $transactions = [];

    public function __construct(...$args)
    {
        parent::__construct(...$args);

        $path = database_path('data/transactions.json');

        if (File::exists($path)) {
            $this->transactions = json_decode(File::get($path), true);
        } else {
            throw new \Exception("Arquivo não encontrado em: {$path}");
        }
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-5 years', 'now');
        $transaction = $this->faker->randomElement($this->transactions);
        $expensePrefix = $this->faker->randomElement([
            'Pagamento', 'Compra', 'Despesa', 'Gasto', 'Transferência'
        ]);

        return [
            'category' =>  $transaction['category'],
            'description' => "$expensePrefix {$transaction['description']}",
            'value' => $this->faker->randomFloat(2, 10, 500),
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }
}