![ContaCorrente](images/favicon.svg)

# ContaCorrente

Tutorial sobre como construir uma aplicação de registro de gastos com Laravel + Livewire.

A aplicação possui suporte a clientes MCP, com autenticação OAuth.

## Sumário

- [1. Criação do projeto](#1-criação-do-projeto)
- [2. Configuração da aplicação](#2-configuração-da-aplicação)
- [3. Preparação da base de dados](#3-preparação-da-base-de-dados)
- [4. Listagem de Transações](#4-listagem-de-transações)
  - [Personalização](#personalização)
- [5. Filtro de Transações](#5-filtro-de-transações)
- [6. MCP Server com OAuth](#6-mcp-server-com-oauth)
  - [Teste com MCP Inspector](#teste-com-mcp-inspector)
  - [Criação de UserInfoTool](#criação-de-userinfotool)
- [7. OAuth](#7-oauth)
  - [Dynamic Client Register](#dynamic-client-register)
  - [Autorização](#autorização)
  - [Protegendo a rota mcp](#protegendo-a-rota-mcp)
  - [Obtenção dos dados reais do usuário](#obtenção-dos-dados-reais-do-usuário)
- [8. Transactions List Tool](#8-transactions-list-tool)
- [9. Gemini CLI](#9-gemini-cli)
- [10. Mais ferramentas](#10-mais-ferramentas)
- [11. Toon: simplificando as respostas](#11-toon-simplificando-as-respostas)
- [Próximos passos](#próximos-passos)
- [Referências](#referências)

## 1. Criação do projeto

Para começar, devemos iniciar as instâncias do docker:

```bash
docker-compose up -d
```

E acessar a instância do `app`:

```bash
docker-compose exec app bash
```

Dentro da instância, vamos criar o projeto do Laravel, usando o Starter Kit do Livewire:

```bash
laravel new contacorrente
```

Usar:
- Livewire
- Laravel's built-in authentication
- Would you like to use Laravel Volt?
    - No
- Pest
- Do you want to install Laravel Boost to improve AI assisted coding?
    - No
- Would you like to run npm install and npm run build?
    - Yes

Esse processo pode levar alguns minutos, dependendo da velocidade de conexão com a internet.

O comando irá criar o diretório `contacorrente`. Mas, como o docker está configurado para executar a partir da raiz de `src`, devemos mover o conteúdo para a raiz do projeto:

```bash
mv contacorrente/* contacorrente/.[!.]* .
```

Com o conteúdo movido, podemos remover o diretório `contacorrente` e sair da instância:

```bash
rm -r contacorrente \
    && exit
```

A aplicação deve estar funcional e disponível em `http://localhost:8080`.

## 2. Configuração da aplicação

Para definir algumas configurações da aplicação, vamos editar o arquivo `.env`. Seguem os valores a serem alterados:

```bash
src/.env
```

```bash
APP_NAME="Conta Corrente"

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo

DB_CONNECTION=pgsql
DB_HOST=database
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=app
```

Com isso, é necessário limpar o cache de configurações do Laravel:

```bash
docker-compose exec app \
    php artisan config:clear
```

E executar a criação da base de dados com:

```bash
docker-compose exec app \
    php artisan migrate
```

A aplicação deve continuar funcional em `http://localhost:8080`, mas agora, com acesso à base de dados no Postgresql (ao invés do sqlite, que é a base inicial padrão do Laravel).

## 3. Preparação da base de dados

A aplicação terá apenas uma tabela e devemos criar uma model (com a *migration*, *seeder* e *factory*) para ela. Vamos usar o seguinte comando:

```bash
docker-compose exec app \
    php artisan make:model -msf Transaction
```

A saída indica os arquivos criados na aplicação:

```bash
   INFO  Model [app/Models/Transaction.php] created successfully.
   INFO  Factory [database/factories/TransactionFactory.php] created successfully.
   INFO  Migration [database/migrations/2025_11_11_234846_create_transactions_table.php] created successfully.
   INFO  Seeder [database/seeders/TransactionSeeder.php] created successfully.
```

A aplicação servirá para registrar gastos simples. Então, a tabela de transações terá a seguinte estrutura:

```bash
- id (int): identificador único da transação;
- user_id (int): referência ao usuário que registrou a transação;
- category (string): a categoria da transação;
- description (string): a descrição da transação;
- value (float): o valor da transação
```

Todos os atributos são obrigatórios.

Precisamos editar o arquivo `create_transactions_table.php` para refletir essa estrutura na tabela:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('description');
            $table->decimal('value', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

E preparar a model `Transaction` para dar suporte ao preenchimento desses atributos:

```bash
src/Models/Transaction.php
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'description',
        'value',
        'created_at',
        'user_id',
    ];

    /**
     * Get the user that owns the Transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Com isso, é possível recriar a base de dados com:

```bash
docker-compose exec app \
    php artisan migrate:fresh
```

Para simplificar o teste da aplicação, vamos editar o *seeder* e o *factory* para gerar 500 entradas aleatórias.

A definição de uma categoria e de uma descrição de forma aleatória pode gerar dados difíceis de testar, por isso, vamos usar um arquivo de apoio, já com algumas definições, para gerar dados mais previsíveis. Devemos criar o arquivo *json* que terá as transações:

```bash
mkdir -p src/database/data \
    && touch src/database/data/transactions.json
```

E editá-lo para ter o seguinte conteúdo:

```bash
src/database/data/transactions.json
```
```json
[
  { "description": "no restaurante", "category": "alimentacao" },
  { "description": "no bar", "category": "alimentacao" },
  { "description": "na padaria", "category": "alimentacao" },
  { "description": "no café", "category": "alimentacao" },
  { "description": "no fast food", "category": "alimentacao" },
  { "description": "na lanchonete", "category": "alimentacao" },
  { "description": "no food truck", "category": "alimentacao" },
  { "description": "na pizzaria", "category": "alimentacao" },
  { "description": "no mercado", "category": "alimentacao" },
  { "description": "de supermercado", "category": "alimentacao" },
  { "description": "na quitanda", "category": "alimentacao" },
  { "description": "na feira livre", "category": "alimentacao" },
  { "description": "em delivery de comida", "category": "alimentacao" },

  { "description": "em transporte", "category": "transporte" },
  { "description": "em aplicativo de corrida", "category": "transporte" },
  { "description": "em passagem de ônibus", "category": "transporte" },
  { "description": "em passagem de metrô", "category": "transporte" },
  { "description": "em estacionamento", "category": "transporte" },
  { "description": "em pedágio", "category": "transporte" },
  { "description": "em manutenção do carro", "category": "transporte" },
  { "description": "em combustível", "category": "transporte" },
  { "description": "em táxi", "category": "transporte" },

  { "description": "com lazer", "category": "lazer" },
  { "description": "no cinema", "category": "lazer" },
  { "description": "no teatro", "category": "lazer" },
  { "description": "em show musical", "category": "lazer" },
  { "description": "em evento esportivo", "category": "lazer" },
  { "description": "em parque de diversões", "category": "lazer" },
  { "description": "em viagem de fim de semana", "category": "lazer" },
  { "description": "em hospedagem", "category": "lazer" },
  { "description": "em bar com amigos", "category": "lazer" },
  { "description": "em assinatura de streaming", "category": "lazer" },
  { "description": "em jogo online", "category": "lazer" },
  { "description": "em plataforma de música", "category": "lazer" },
  { "description": "em aplicativo de filmes", "category": "lazer" },

  { "description": "na farmácia", "category": "saude" },
  { "description": "em consulta médica", "category": "saude" },
  { "description": "em exames laboratoriais", "category": "saude" },
  { "description": "em academia", "category": "saude" },
  { "description": "em plano de saude", "category": "saude" },
  { "description": "em produtos de higiene", "category": "saude" },
  { "description": "em massagem", "category": "saude" },
  { "description": "em dentista", "category": "saude" },

  { "description": "em curso online", "category": "educacao" },
  { "description": "em curso na Coursera", "category": "educacao" },
  { "description": "em curso na Udemy", "category": "educacao" },
  { "description": "em curso na Skillshare", "category": "educacao" },
  { "description": "em curso na Alura", "category": "educacao" },
  { "description": "em mensalidade escolar", "category": "educacao" },
  { "description": "em material didático", "category": "educacao" },
  { "description": "em livro", "category": "educacao" },
  { "description": "em ebook", "category": "educacao" },
  { "description": "em assinatura de plataforma educacional", "category": "educacao" },
  { "description": "em assinatura de artigos científicos", "category": "educacao" },

  { "description": "em conta de luz", "category": "moradia" },
  { "description": "em conta de água", "category": "moradia" },
  { "description": "em conta de internet", "category": "moradia" },
  { "description": "em conta de celular", "category": "moradia" },
  { "description": "em condomínio", "category": "moradia" },
  { "description": "em taxa bancária", "category": "moradia" },
  { "description": "em manutenção residencial", "category": "moradia" },

  { "description": "em presente", "category": "outros" },
  { "description": "em doação", "category": "outros" },
  { "description": "com compra de roupa", "category": "outros" },
  { "description": "em produto de beleza", "category": "outros" },
  { "description": "em cuidados pessoais", "category": "outros" },
  { "description": "em utensílios domésticos", "category": "outros" },
  { "description": "em eletrônicos", "category": "outros" },
  { "description": "em mobília", "category": "outros" },
  { "description": "em papelaria", "category": "outros" },
  { "description": "em pet shop", "category": "outros" },
  { "description": "em supermercado local", "category": "outros" },
  { "description": "em padaria artesanal", "category": "outros" }
]
```

Devemos editar também a classe `TransactionFactory`, para que use o arquivo *json* ao gerar um registro de transação:

```bash
src/database/factories/TransactionFactory.php
```

```php
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
```

A classe `TransactionSeeder` agora pode ser implementada. Ela irá registrar 500 transações para cada usuário na base. E irá criar o usuário padrão (que iremos usar em nossos testes), caso ainda não exista.

```bash
src/database/seeders/TransactionSeeder.php
```

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('transactions')->truncate();

        // Default user
        User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'User',
                'password' => bcrypt('user123'),
            ]
        );

        foreach (User::all() as $user) {
            Transaction::factory()
                ->count(500)
                ->for($user)
                ->create();
        }
    }
}
```

Dessa forma, sempre que for necessário, a tabela `transactions` será preenchida com 500 entradas aleatórias para cada usuário de teste registrado, com o comando:

```bash
docker-compose exec app \
    php artisan db:seed --class=TransactionSeeder
```

O comando pode ser executado a qualquer momento. Porém, a tabela `transactions` sempre será esvaziada e 500 novos registros serão criados para cada usuário. Portanto, é bom NUNCA usar isso em produção.

Para se verificar se a geração dos registros funcionou, pode-se utilizar:

```bash
docker-compose exec app \
    php artisan tinker --execute "echo \App\Models\Transaction::count()"
```

A resposta deve ser: 500.

E também, pode-se verificar o primeiro registro:

```bash
docker-compose exec app \
    php artisan tinker --execute "dump(\App\Models\Transaction::first()->toArray())"
```

## 4. Listagem de Transações

Vamos criar um componente **Livewire** para exibição das transações do usuário, para ser exibido na dashboard:

```bash
docker-compose exec app \
    php artisan livewire:make TransactionsList
```

O comando irá criar uma classe e uma view para o componente:

```bash
 COMPONENT CREATED  🤙

CLASS: app/Livewire/TransactionsList.php
VIEW:  resources/views/livewire/transactions-list.blade.php
```

Vamos editar a classe `TransactionList` para obter as transações da base de dados:

```bash
src/app/Livewire/TransactionsList.php
```

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class TransactionsList extends Component
{
    public array $transactions = [];

    public function mount(): void
    {
        $this->transactions = Transaction::query()
            ->where('user_id', Auth::id())
            ->get()
            ->all();
    }

    public function render()
    {
        return view('livewire.transactions-list');
    }
}
```

E vamos editar o componente visual associado (`transaction-list.blade.php`) para exibir a listagem das transações:

```bash
src/resources/views/livewire/transactions-list.blade.php
```

```php
<div class="flex flex-1 flex-col gap-6 h-full">
    <div class="flex flex-1 w-full">
        <h2 class="text-2xl font-bold uppercase">Dashboard</h2>
    </div>
    <div class="flex flex-col gap-2">
        @foreach ($transactions as $transaction)
            <div class="border rounded-md border-neutral-200 dark:border-neutral-700 p-4 flex justify-between" wire:key="{{ $transaction->id }}">
                <div>
                    <div class="flex gap-4 items-center">
                        <div class="text-xs">{{ $transaction->created_at->format('d/m/Y H:i') }}</div>
                        <div class="text-[.6rem] uppercase bg-gray-700 text-white rounded-md px-2 dark:bg-white dark:text-black">{{ $transaction->category}}</div>
                    </div>
                    <div class="font-bold text-lg">{{ $transaction->description }}</div>
                    <div><span class="text-xs font-bold">R$</span> {{ $transaction->value }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
```

Com isso, podemos utilizar o componente na dashboard:

```bash
src/resources/views/dashboard.blade.php
```

```php
<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <livewire:transactions-list>
    </div>
</x-layouts.app>
```

Como o componente utiliza algumas classes não compiladas do *tailwind*, é necessário reconstruir os recursos css, com:

```bash
docker-compose exec app \
    npm run build
```

E, ao fazer o login na aplicação:

```bash
Email address: user@example.com
Password: user123
```

A lista com as 500 transações do usuário será exibida.

### Personalização

Nesse ponto, é bom alterar o nome da aplicação que aparece na barra lateral:

```bash
src/resources/views/components/app-logo.blade.php
```

```html
<div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
    <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
</div>
<div class="ms-1 grid flex-1 text-start text-sm">
    <span class="mb-0.5 truncate leading-tight font-semibold">{{ config('app.name') }}</span>
</div>
```

Também o título da aplicação na tela de entrada, substituindo a entrada `<title>Laravel</title>` pelo nome da aplicação obtido da configuração:

```bash
src/resources/views/welcome.blade.php
```

```php
<title>{{ config('app.name') }}</title>
```

E atualizar também o logo.

```bash
src/resources/views/components/app-logo-icon.blade.php
```

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 112.000000 236.000000" {{ $attributes }}>
<g transform="translate(0.000000,236.000000) scale(0.100000,-0.100000)">
<path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M499 2211 l-29 -29 0 -242 0 -242 29 -29 c24 -23 38 -29 74 -29 38 0 49 5 71 31 l26 31 0 238 0 238 -26 31 c-22 26 -33 31 -71 31 -36 0 -50 -6 -74 -29z"/>
<path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M700 1985 c0 -113 0 -115 33 -160 l32 -47 3 -169 3 -169 110 0 109 0 0 153 c0 215 -22 292 -114 393 -37 41 -145 114 -170 114 -3 0 -6 -52 -6 -115z"/>
<path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M355 2061 c-68 -39 -130 -109 -168 -189 -32 -67 -32 -68 -35 -259 -6 -271 14 -361 100 -458 31 -36 121 -95 146 -95 9 0 12 30 12 116 0 101 -3 119 -20 141 -19 24 -20 41 -20 237 l0 212 30 47 c29 46 30 52 30 162 0 85 -3 115 -12 115 -7 0 -36 -13 -63 -29z"/>
<path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M499 1421 l-29 -29 0 -242 0 -242 29 -29 c24 -23 38 -29 74 -29 38 0 49 5 71 31 l26 31 0 238 0 238 -26 31 c-22 26 -33 31 -71 31 -36 0 -50 -6 -74 -29z"/>
<path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M730 1131 c0 -103 3 -125 20 -153 18 -30 20 -51 20 -231 0 -214 -5 -242 -54 -286 -25 -24 -26 -28 -26 -143 0 -65 3 -118 6 -118 3 0 31 12 62 28 71 34 152 114 186 183 40 83 48 152 44 368 -4 216 -15 262 -78 348 -30 41 -117 106 -157 118 -23 6 -23 6 -23 -114z"/>
<path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M150 717 c0 -155 12 -232 47 -304 31 -63 107 -141 173 -177 l50 -27 0 126 c0 116 -2 130 -22 158 -21 30 -23 45 -26 195 l-3 162 -110 0 -109 0 0 -133z"/>
<path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M489 601 l-29 -29 0 -242 0 -242 29 -29 c24 -23 38 -29 74 -29 38 0 49 5 71 31 l26 31 0 238 0 238 -26 31 c-22 26 -33 31 -71 31 -36 0 -50 -6 -74 -29z"/>
</g>
</svg>
```

Vamos mudar o ícone da aplicação também:

```bash
cp images/* src/public/
```

## 5. Filtro de Transações

Vamos alterar a listagem para permitir filtrar as transações.

Primeiro, vamos editar o componente `TransactionList` para dar suporte aos filtros e aplicação dos filtros:

```bash
src/app/Livewire/TransactionsList.php
```

```php
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
```

Agora, o componente recria a lista de transação a cada alteração de um dos filtros (métodos *updated*). Além disso, fornece para a *view* as categorias disponíveis dentre as transações do usuário.

Podemos alterar a *view* do componente para montar os filtros e indicar quando qualquer um deles foi alterado (atributos `wire:model.live`):

```bash
src/resources/views/livewire/transactions-list.blade.php
```

```php
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
```

Como muitas novas classes do *Tailwind* foram utilizadas, é necessário compilar novamente os recursos *CSS* com:

```bash
docker-compose exec app \
    npm run build
```

Ao acessar a aplicação, é possível efetuar o filtro por:
- Descrição
- Categoria
- Data inicial
- Data final

A visualização também exibe a quantidade de transações filtradas e o total dessas transações. Isso será útil para validar as respostas das ferramentas do MCP.

## 6. MCP Server com OAuth

Vamos instalar o pacote do Laravel para dar suporte ao MCP:

```bash
docker-compose exec app \
    composer require laravel/mcp
```

Vamos criar a classe que vai servir o endpoint do *MCP Server*:

```bash
docker-compose exec app \
    php artisan make:mcp-server McpServer
```

Essa classe contém as informações do servidor e a lista de ferramentas, recursos e prompts que ele fornece.

Vamos editar para ter uma descrição melhor. A descrição ajudará ao LLM Client a entender do que o servidor é capaz.

```bash
src/app/Mcp/Servers/McpServer.php
```

```php
<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;

class McpServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Mcp Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Expense management tools for recording and listing transactions.
        Each transaction includes:
        - Creation date;
        - Category;
        - Description; and
        - Value
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        //
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
```

Por enquanto, só há a descrição para os *LLMs clientes* sobre o que o servidor fornece. Mas, já podemos habilitar o endpoint para acesso a esse servidor. Primeiro, criando o arquivo de rotas, com:

```bash
docker-compose exec app \
    php artisan vendor:publish --tag=ai-routes
```

E editando o arquivo para usar a classe do servidor:

```bash
src/routes/ai.php
```

```php
<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', \App\Mcp\Servers\McpServer::class);
```

É possível verificar se a rota está disponível, com:

```bash
docker-compose exec app \
    php artisan route:list --path mcp
```

Devem ser exibidas duas rotas: uma para *GET|HEAD* e outra para *POST*.

Antes de testarmos o servidor, precisamos exportar a configuração das políticas de *CORS* da aplicação:

```bash
docker-compose exec app \
    php artisan config:publish cors
```

E adicionar os endpoints de exceção: no caso, apenas a rota `/mcp`. Edite o valor de `paths` para conter o `/mcp`:

```bash
src/config/cors.php
```

```php
'paths' => ['api/*', 'sanctum/csrf-cookie', 'mcp*'],
```

### Teste com MCP Inspector

Nesse momento, podemos usar o [MCP Inspector](https://github.com/modelcontextprotocol/inspector) para testar o servidor.

O MCP Inspector é uma ferramenta capaz de conversar com servidores MCP remotos. Com ele, é possível verificar as ferramentas, recursos e prompts que a aplicação fornece.

Vamos usar a versão docker da ferramenta, para simplificar o processo de instalação.

```bash
docker run --rm --network host -p 6274:6274 -p 6277:6277 -e MCP_AUTO_OPEN_ENABLED=false -it ghcr.io/modelcontextprotocol/inspector:latest
```

Caso dê algum erro, pode ser necessário fazer login no github para conseguir baixar a imagem. Efetue o login, e tente executar a ferramenta novamente.

```bash
docker login ghcr.io
```

Com o container em execução, basta acessar a url fornecida por ele e, no **MCP Inspector**, configurar as seguintes opções:

```
Transport Type: Streamable HTTP
URL: http://localhost:8080/mcp
Connection Type: Direct
```

Ao clicar em **Connect** o status deve indicar **Connected**, com um sinal verde.

Ao tentar listar os *Resources*, *Prompts* ou *Tools*, nenhum erro deve ocorrer. Porém, nada deverá ser exibido.

### Criação de UserInfoTool

Vamos criar então uma *Tool* simples, para testar a integração com o MCP Inspector:

```bash
docker-compose exec app \
    php artisan make:mcp-tool UserInfoTool
```

Não vamos acessar a base de dados na ferramenta. Vamos apenas retornar alguns dados fixos de usuário só para validar a integração.

```bash
src/app/Mcp/Tools/UserInfoTool.php
```

```php
<?php

namespace App\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UserInfoTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get user information.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        return Response::json([
            'name' => 'ValidUser',
            'email' => 'valid-user@test.com'
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            //
        ];
    }
}
```

Para ela ficar disponível para o **MCP Inspector**, precisamos adicioná-la ao `McpServer`, dentro do atributo `tools`.

```bash
src/app/Mcp/Servers/McpServer.php
```

```php
<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\UserInfoTool;
use Laravel\Mcp\Server;

class McpServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Mcp Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Expense management tools for recording and listing transactions.
        Each transaction includes:
        - Creation date;
        - Category;
        - Description; and
        - Value
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        UserInfoTool::class
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
```

Agora, no **MCP Inspector**, ao listar as ferramentas, a **user-info-tool** deve aparecer. Ela pode, inclusive, ser executada. O resultado esperado é:

```json
{
  "name": "ValidUser",
  "email": "valid-user@test.com"
}
```

## 7. OAuth

A ferramenta **user-info-tool** devolve um valor fixo pois no **MCP Server** não há informação de usuário logado. A rota MCP não tem qualquer tipo de autenticação.

Vamos mudar isso adicionando autenticação OAuth ao projeto. O **Laravel** possui o pacote [Laravel Passport](https://laravel.com/docs/12.x/passport) que é especializado em fornecer essa funcionalidade. Vamos adicionar essa dependência:

```bash
docker-compose exec app \
    php artisan install:api --passport
```

A partir daqui, podemos seguir a [documentação](https://laravel.com/docs/12.x/passport) do próprio **Laravel Passport**.

Primeiro, precisamos editar a model `User`, para indicar que um usuário pode ter tokens de acesso às APIs:

```bash
src/app/Models/User.php
```

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
```

Então, devemos habilitar o **Passport** como um *guarda* para as urls marcadas com o middleware `auth:api`.

```bash
src/config/auth.php
```

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],
```

### Dynamic Client Register

Aplicações de terceiros poderão se registrar automaticamente na nossa aplicação usando a funcionalidade de DCR (Dynamic Client Registration) disponibilizada pelos pacotes **Laravel MCP** e **Laravel Passport**. Para habilitar essa funcionalidade, deve-se editar o arquivo de rotas do MCP e indicar que queremos publicar as urls de descoberta:

```bash
src/routes/ai.php
```

```php
<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp', \App\Mcp\Servers\McpServer::class);
```

Essas rotas fornecem aos clientes as informações necessárias para proceder com o registro e autorização de usuários.

Ao executar o comando:

```bash
docker-compose exec app \
    php artisan route:list --path oauth
```

Podemos ver rotas de descoberta (`.well-known`) e de manutenção do *OAuth* (`oauth`). O resultado deve ser:

```bash

  GET|HEAD   .well-known/oauth-authorization-server/{path?} ...................................................................................................... mcp.oauth.authorization-server
  GET|HEAD   .well-known/oauth-protected-resource/{path?} .......................................................................................................... mcp.oauth.protected-resource
  GET|HEAD   oauth/authorize ........................................................................... passport.authorizations.authorize › Laravel\Passport › AuthorizationController@authorize
  POST       oauth/authorize ........................................................................ passport.authorizations.approve › Laravel\Passport › ApproveAuthorizationController@approve
  DELETE     oauth/authorize ................................................................................. passport.authorizations.deny › Laravel\Passport › DenyAuthorizationController@deny
  GET|HEAD   oauth/device ......................................................................................................... passport.device › Laravel\Passport › DeviceUserCodeController
  GET|HEAD   oauth/device/authorize ................................................................. passport.device.authorizations.authorize › Laravel\Passport › DeviceAuthorizationController
  POST       oauth/device/authorize ............................................................ passport.device.authorizations.approve › Laravel\Passport › ApproveDeviceAuthorizationController
  DELETE     oauth/device/authorize .................................................................. passport.device.authorizations.deny › Laravel\Passport › DenyDeviceAuthorizationController
  POST       oauth/device/code ................................................................................................... passport.device.code › Laravel\Passport › DeviceCodeController
  POST       oauth/register ............................................................................................................................... Laravel\Mcp › OAuthRegisterController
  POST       oauth/token ................................................................................................... passport.token › Laravel\Passport › AccessTokenController@issueToken
  POST       oauth/token/refresh ................................................................................... passport.token.refresh › Laravel\Passport › TransientTokenController@refresh
```

Essas rotas também precisam estar na lista do **CORS**. Por isso, precisamos adicionar mais essas exceções:

```bash
src/config/cors.php
```

```php
'paths' => ['api/*', 'sanctum/csrf-cookie', 'mcp*', '.well-known/*', 'oauth/*'],
```

### Autorização

Depois de se registrar, um cliente precisa solicitar permissão do usuário para poder acessar os dados. Quando isso ocorrer, uma tela de autorização deverá ser exibida, solicitando a confirmação (ou negação) do usuário.

O pacote **Laravel MCP** fornece uma visualização pronta para isso. Para utilizarmos, precisamos fazer duas coisas. A primeira é publicar a **view** com:

```bash
docker-compose exec app \
    php artisan vendor:publish --tag=mcp-views
```

Depois, precisamos configurar o **Passport** para utilizá-la, editando a classe `AppServiceProvider`:

```bash
src/app/Providers/AppServiceProvider.php
```

```php
<?php

namespace App\Providers;

use Laravel\Passport\Passport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::authorizationView(function ($parameters) {
            return view('mcp.authorize', $parameters);
        });
    }
}
```

Porém, a *view* padrão do pacote *mcp* do Laravel - nessa versão pelo menos - não está no padrão do **Livewire**. Segue abaixo uma implementação melhor:

```bash
src/resources/views/mcp/authorize.blade.php
```

```php
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <div class="flex min-h-screen items-center justify-center p-6">
            <div class="w-full max-w-md space-y-6">
                <div class="flex items-center gap-3 justify-center">
                    <x-app-logo />
                </div>

                <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
                    {{-- Header --}}
                    <div class="px-6 pt-6 pb-4 text-center">
                        <svg class="mx-auto h-12 w-12 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 3l7 4v5c0 5-3.5 9-7 9s-7-4-7-9V7l7-4z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4"/>
                        </svg>

                        <h2 class="mt-4 text-xl font-semibold">
                            {{ __('Authorize :name', ['name' => $client->name]) }}
                        </h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ __('This application will be able to use available MCP features.') }}
                        </p>
                    </div>

                    {{-- Content --}}
                    <div class="px-6 pb-6 space-y-5">
                        <div class="rounded-lg border bg-muted/50 p-4">
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">
                                {{ __('Connected as') }}
                            </p>
                            <p class="mt-1 font-medium">
                                {{ $user->email }}
                            </p>
                        </div>

                        @if(count($scopes) > 0)
                            <div>
                                <p class="text-sm font-medium">
                                    {{ __('Requested permissions') }}
                                </p>
                                <ul class="mt-2 space-y-2">
                                    @foreach($scopes as $scope)
                                        <li class="flex items-start gap-2">
                                            <span class="mt-1 inline-block h-2 w-2 rounded-full bg-primary"></span>
                                            <span class="text-sm text-muted-foreground">
                                                {{ $scope->description }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="px-6 pb-6" x-data="{ loading: false }">
                        <div class="grid grid-cols-2 gap-3">
                            {{-- Deny --}}
                            <form method="POST" action="{{ route('passport.authorizations.deny') }}"
                                x-on:submit="loading = true" class="contents">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="state" value="">
                                <input type="hidden" name="client_id" value="{{ $client->id }}">
                                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:opacity-50 w-full"
                                    x-bind:disabled="loading"
                                >
                                    <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    {{ __('Cancel') }}
                                </button>
                            </form>

                            {{-- Approve --}}
                            <form method="POST" action="{{ route('passport.authorizations.approve') }}"
                                x-on:submit="loading = true" class="contents" id="authorizeForm">
                                @csrf
                                <input type="hidden" name="state" value="">
                                <input type="hidden" name="client_id" value="{{ $client->id }}">
                                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:opacity-50 w-full"
                                    x-bind:disabled="loading"
                                    x-bind:class="{ 'cursor-not-allowed': loading }"
                                >
                                    <svg x-show="loading" class="-ml-1 mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <span x-text="loading ? '{{ __('Authorizing...') }}' : '{{ __('Authorize') }}'"></span>
                                </button>
                            </form>
                        </div>

                        <p class="mt-4 text-xs text-center text-muted-foreground">
                            {{ __('You can revoke access at any time in your account settings.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
```

Antes de prosseguirmos, devemos atualizar os recursos do frontend:

```bash
docker-compose exec app npm run build
```

Enfim, com essas alterações, é possível fazer o ciclo completo de autenticação OAuth no **MCP Inspector**:
- Tente se conectar novamente. Como agora exigimos autenticação, o **MCP Inspector** não irá conseguir;
- Selecione **Open Auth Settings** e clique em **Quick OAuth Flow**.
- A tela de autorização será aberta (ou a de login, caso não esteja autenticado) e o fluxo será executado com sucesso após a autorização.

### Protegendo a rota mcp/

Com a autenticação OAuth funcionando, podemos alterar a rota `mcp` para ficar protegida pela autenticação:

```bash
src/routes/ai.php
```

```php
<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp', \App\Mcp\Servers\McpServer::class)
    ->middleware('auth:api');
```

### Obtenção dos dados reais do usuário

Como agora temos um usuário autenticado ao acessar o **MCP Server**, podemos alterar `UserInfoTool` para retornar dados reais.

```bash
src/app/Mcp/Tools/UserInfoTool.php
```

```php
<?php

namespace App\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UserInfoTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get user information.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $user = $request->user();

        return Response::json([
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            //
        ];
    }
}
```

Para se verificar que tudo está funcionando, no **MCP Inspector** pode-se conectar novamente ao servidor MCP e executar a ferramenta **user-info-tool**. O resultado deve ter as informações do usuário.

## 8. Transactions List Tool

Vamos fornecer um modo de listar as transações do usuário autenticado via ferramenta no **MCP**:

```bash
docker-compose exec app \
    php artisan make:mcp-tool ListTransactionsTool
```

Vamos copiar e adaptar o código do componente de listagem `TransactionsList` para a ferramenta criada `ListTransactionsTool`:

```bash
src/app/Mcp/Tools/ListTransactionsTool.php
```

```php
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
```

A ferramenta contém instruções claras sobre como o *cliente LLM* deve utilizar a ferramenta. Essas instruções são fundamentais para o bom funcionamento dessa integração.

Para que a ferramenta fique disponível, devemos adicioná-la ao servidor `McpServer`:

```bash
src/app/Mcp/Servers/McpServer.php
```

```php
<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListTransactionsTool;
use App\Mcp\Tools\UserInfoTool;
use Laravel\Mcp\Server;

class McpServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Mcp Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Expense management tools for recording and listing transactions.
        Each transaction includes:
        - Creation date;
        - Category;
        - Description; and
        - Value
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        UserInfoTool::class,
        ListTransactionsTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
```

Ao atualizar a lista de ferramentas no  **MCP Inspector**, a nova ferramenta será exibida. E, ao executar a listagem, pode-se verificar que o **MCP Inspector** utiliza o `schema` da ferramenta para montar o formulário do filtro.

## 9. Gemini CLI

Com o servidor configurado e duas ferramentas disponíveis, podemos usar um cliente LLM para testar o potencial da integração com a aplicação. Vamos utilizar o Gemini CLI. Primeiro, é necessário instalá-lo seguindo as orientações do [repositório](https://github.com/google-gemini/gemini-cli).

Se o ambiente tiver o `npx` instalado, basta executar:

```bash
npx https://github.com/google-gemini/gemini-cli
```

O arquivo `~/.gemini/settings.json` deve ser editado para acessar o servidor MCP da aplicação:

```json
{
  "mcpServers": {
    "contacorrente": {
      "httpUrl": "http://localhost:8080/mcp",
      "oauth": {
        "enabled": true,
        "scopes": ["mcp:use"]
      }
    }
  }
}
```

Com isso, basta reiniciar o **gemini**. Ele irá reconhecer o *MCP Server* e vai indicar que uma autenticação é necessária. Digite o comando:

```bash
/mcp auth contacorrente
```

O **Gemini** vai abrir a autorização da aplicação e assim que for autorizado vai configurar o token de acesso. Depois disso, é possível testar o uso da ferramenta de listagem. Por exemplo:

```bash
Qual o total e a quantidade de gastos com educação neste ano?
```

O **Gemini** solicitará acesso à ferramenta de listagem e a usará como fonte de dados, quando autorizado. Dessa forma, podemos extrair informações da aplicação e fazer análises a partir do chat. Por exemplo, dando sequência:

```bash
Compare com o ano anterior e tabele.
```

E, por fim:

```bash
Faça isso para os últimos 4 anos. Depois, faça uma análise desses gastos, classificando-os por categoria.
```

## 10. Mais ferramentas

Agora que temos a listagem, podemos adicionar ferramentas para adicionar e editar transações e também, listar as categorias dos gastos do usuário:

```bash
docker-compose exec app php artisan make:mcp-tool AddTransactionTool \
    && docker-compose exec app php artisan make:mcp-tool EditTransactionTool \
    && docker-compose exec app php artisan make:mcp-tool ListCategoriesTool
```

Vamos editar essas ferramentas, começando pela `AddTransactionTool`:

```bash
src/app/Mcp/Tools/AddTransactionTool.php
```

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use App\Models\Transaction;
use Laravel\Mcp\Server\Tool;
use Illuminate\JsonSchema\JsonSchema;

class AddTransactionTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Adds a new spending transaction.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'category' => $request->get('category'),
            'description' => $request->get('description'),
            'value' => $request->get('value'),
            'created_at' => $request->get('createdAt') ?? now(),
        ]);

        return Response::text("Transaction successfully added, with identifier: {$transaction->id}");
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'createdAt' => $schema->string()
                ->format('date')
                ->description('Transaction date (optional)')
                ->nullable(),

            'description' => $schema->string()
                ->description('Transaction description')
                ->required(),

            'category' => $schema->string()
                ->description('Transaction category. Slug-like format (lowercase, no accents or symbols)')
                ->required(),

            'value' => $schema->number()
                ->description('Transaction value')
                ->required(),
        ];
    }
}
```

Agora, `EditTransactionTool`:

```bash
src/app/Mcp/Tools/EditTransactionTool.php
```

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use App\Models\Transaction;
use Laravel\Mcp\Server\Tool;
use Illuminate\JsonSchema\JsonSchema;

class EditTransactionTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Edit the details of an existing spending transaction.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $transaction = Transaction::where([
            'id' => $request->get('id'),
            'user_id' => $request->user()->id,
        ])->first();

        if (!$transaction) {
            return Response::error("Transaction not found or you do not have permission to edit it.");
        }

        $transaction->update([
            'category' => $request->get('category') ?? $transaction->category,
            'description' => $request->get('description') ?? $transaction->description,
            'value' => $request->get('value') ?? $transaction->value,
            'created_at' => $request->get('createdAt') ?? $transaction->created_at,
        ]);

        return Response::text("Transaction successfully updated.");
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Unique transaction identifier')
                ->required(),

            'createdAt' => $schema->string()
                ->format('date')
                ->description('Transaction date')
                ->nullable(),

            'description' => $schema->string()
                ->description('Transaction description')
                ->nullable(),

            'category' => $schema->string()
                ->description('Transaction category. Slug-like format (lowercase, no accents or symbols)')
                ->nullable(),

            'value' => $schema->number()
                ->description('Transaction value')
                ->nullable(),
        ];
    }
}
```

E por fim `ListCategoriesTool`:

```bash
src/app/Mcp/Tools/ListCategoriesTool.php
```

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use App\Models\Transaction;
use Laravel\Mcp\Server\Tool;
use Illuminate\JsonSchema\JsonSchema;

class ListCategoriesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Lists all categories used in transactions.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $query = Transaction::query()
            ->select('category')
            ->distinct()
            ->where('user_id', $request->user()->id)
            ->orderBy('category');

        $categories = $query
            ->pluck('category')
            ->all();

        return Response::json($categories);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
        ];
    }
}
```

Devemos adicionar as ferramentas ao MCP Server:

```bash
src/app/Mcp/Servers/McpServer.php
```

```php
<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use App\Mcp\Tools\UserInfoTool;
use App\Mcp\Tools\AddTransactionTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\EditTransactionTool;
use App\Mcp\Tools\ListTransactionsTool;

class McpServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Mcp Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Expense management tools for recording and listing transactions.
        Each transaction includes:
        - Creation date;
        - Category;
        - Description; and
        - Value
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        UserInfoTool::class,
        ListTransactionsTool::class,
        AddTransactionTool::class,
        EditTransactionTool::class,
        ListCategoriesTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
```

Ao reiniciar o **gemini**, teremos acesso às novas ferramentas. Podemos verificar isso com o comando `/mcp desc`. E podemos interagir com a aplicação, por exemplo, da seguinte forma:

```bash
Registre um gasto com educação que tive hoje: "Talk de Laravel com Livewire para criação de um MCP Server". O custo foi de 5 reais.
```

E podemos editar a transação:

```bash
Edite o título para "Tech Talk sobre 'Laravel com Livewire para criação de um MCP Server'".
```

## 11. Toon: simplificando as respostas

O formato json possui muita informação redundante e isso pode gerar custos de tráfego dos dados e, sobretudo, de tokens consumidos pelo LLM cliente. Por isso, podemos utilizar um formato otimizado para esse fim: [TOON](https://github.com/toon-format/toon).

Vamos adicionar um pacote que faz a conversão dos objetos para esse formato e atualizar a ferramenta de listagem de transações para utilizar esse formato:

```bash
docker-compose exec app \
    composer require helgesverre/toon
```

Com isso, temos o necessário para modificar o retorno de JSON para TOON. Podemos alterar `ListTransactionsTool` e modificar o retorno do método `handle`:

```bash
src/app/Mcp/Tools/ListTransactionsTool.php
```

```php
....
use HelgeSverre\Toon\Toon;

class ListTransactionsTool extends Tool
{
    ....

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        ....

        return Response::text(Toon::encode($transactions->all()));
    }

    ....
}
```

Com essa simples alteração, o retorno da ferramenta consumirá muito menos dados e tokens.

## Próximos passos

A partir daqui, é possível expandir a aplicação com algumas funcionalidades:
- Para o MCP: pode-se criar novas ferramentas como exclusão de transações e relatórios personalizados;
- Para a aplicação: inclusão, edição e exclusão manual de gastos; paginação dos gastos na dashboard e no MCP;

Além disso, deve-se atentar para configurações de segurança:
- Permitir apenas clientes conhecidos a fazer registro automático na aplicação;
- Controlar a quantidade de requisições em curto espaço de tempo na url `mcp`;
- Criar escopos limitados e granular as permissões de acesso aos dados para que os usuários decidam o que querem ou não compartilhar com as aplicações de terceiros;

A documentação do Livewire e do Laravel MCP são bem ricas e podem facilitar a implementação dessas evoluções.

## Referências

- [Laravel Starter Kits](https://laravel.com/starter-kits)
- [Laravel Livewire](https://livewire.laravel.com/)
- [Model Context Protocol (MCP)](https://modelcontextprotocol.io/docs/getting-started/intro)
- [Laravel MCP](https://laravel.com/docs/12.x/mcp)
- [Laravel Passport](https://laravel.com/docs/12.x/passport)
- [MCP Inspector](https://github.com/modelcontextprotocol/inspector)
- [Gemini CLI](https://github.com/google-gemini/gemini-cli)
- [JSON vs TOON — A new era of structured input?](https://medium.com/medialesson/json-vs-toon-a-new-era-of-structured-input-19cbb7fc552b)
- [Curso Laracasts sobre Livewire 3](https://laracasts.com/series/livewire-3-from-scratch)
- [Laravel Boost](https://github.com/laravel/boost)
