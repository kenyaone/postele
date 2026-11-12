<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Credit sales (debts) and repayments, order type (dine-in / Glovo), and the Glovo menu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('channel', ['house', 'glovo'])->default('house')->after('name');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('channel', ['house', 'glovo'])->default('house')->after('status');
        });
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash','kk_stk','kk_till_auto','kk_till_manual','credit') NOT NULL");

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->index();
            $table->string('phone', 20)->nullable();
            $table->string('notes', 255)->nullable();
            $table->boolean('is_platform')->default(false);   // Glovo and other delivery platforms
            $table->timestamps();
        });
        Schema::create('credit_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->date('due_on')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });
        Schema::create('credit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->decimal('amount', 10, 2);                 // money received
            $table->decimal('fees', 10, 2)->default(0);       // kept back, e.g. Glovo commission
            $table->enum('method', ['cash', 'mpesa', 'bank', 'other']);
            $table->string('reference', 60)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('received_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'received_at']);
        });

        // Glovo account and menu (prices from the Glovo partner portal, 25 Oct 2026).
        $now = now();
        if (! DB::table('customers')->where('is_platform', true)->where('name', 'Glovo')->exists()) {
            DB::table('customers')->insert(['name' => 'Glovo', 'notes' => 'Delivery platform: pays out after commission', 'is_platform' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $menu = [
            'Ugali' => [['Ugali Plain', 100], ['Ugali Sukuma', 150], ['Ugali Managu', 250], ['Ugali Managu Special', 300], ['Ugali Mayai', 180], ['Ugali Nyama', 400], ['Ugali Kuku', 550]],
            'Sides & Extras' => [['Kachumbari Salad', 100], ['Plain Fries', 200], ['Rollex', 180]],
            'Plain Dishes' => [['Beef Plain', 200], ['Pork Plain', 350], ['Pork Ugali', 450], ['Fish', 550], ['Cabbage Plain', 70], ['Managu Plain', 200], ['Sukuma Plain', 70]],
            'Rice & Pilau' => [['Rice', 150], ['Rice Beans', 250], ['Rice Beef', 400], ['Pilau', 240]],
            'Chapati & Beans' => [['Chapati', 50], ['Chapati Beans / Ndengu', 200], ['2 Chapati With Beans / Ndengu', 220], ['Chapo Beef', 300], ['Beans Plain', 150], ['Githeri', 150], ['Githeri Special', 250]],
            'Snacks' => [['5Pcs Mandazi', 100], ['Samosa 2 Pcs', 100], ['Smokie 2 Pcs', 140], ['Plain Eggs', 100], ['Viazi Karai', 140], ['Chips Masala', 300], ['Chips Full', 300]],
            'Chicken' => [['1/4 Chicken', 400], ['1/4 Chicken & Chips', 450], ['1/2Chicken & Chips', 700]],
            'Drinks' => [['Black Tea', 100], ['Milk Tea', 150], ['Coca-Cola Original 500Ml Pet', 100], ['Fanta Orange 500Ml Pet', 100], ['Sprite Lemon 500Ml Pet', 100]],
            'Combos' => [['1/4 Chicken + Chips + Soda', 550], ['Chips + Smokie + Soda', 450], ['Pilau + Kachumbari + Soda', 350]],
        ];
        $sort = (int) DB::table('categories')->max('sort_order') + 1;
        foreach ($menu as $group => $items) {
            $name = 'Glovo: ' . $group;
            $catId = DB::table('categories')->where('name', $name)->value('id')
                ?? DB::table('categories')->insertGetId(['name' => $name, 'channel' => 'glovo', 'sort_order' => $sort++, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($items as [$item, $price]) {
                if (! DB::table('items')->where('category_id', $catId)->where('name', $item)->exists()) {
                    DB::table('items')->insert(['category_id' => $catId, 'name' => $item, 'price' => $price, 'station' => 'kitchen', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        // Glovo categories and items are left in place (past orders may use them); turn them off in Menu if needed.
        Schema::dropIfExists('credit_payments');
        Schema::dropIfExists('credit_sales');
        Schema::dropIfExists('customers');
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash','kk_stk','kk_till_auto','kk_till_manual') NOT NULL");
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('channel'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('channel'));
    }
};
