<?php

use App\Models\Customer;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('order_comments')
            ->select('id', 'user_id')
            ->whereNotNull('user_id')
            ->where(function ($q) {
                $q->whereNull('user_type')->orWhere('user_type', '');
            })
            ->get();

        foreach ($rows as $row) {
            $type = null;
            if (DB::table('customers')->where('id', $row->user_id)->exists()) {
                $type = Customer::class;
            } elseif (DB::table('users')->where('id', $row->user_id)->exists()) {
                $type = User::class;
            } elseif (DB::table('sellers')->where('id', $row->user_id)->exists()) {
                $type = Seller::class;
            }
            if ($type !== null) {
                DB::table('order_comments')->where('id', $row->id)->update(['user_type' => $type]);
            }
        }
    }

    public function down(): void
    {
        // Не откатываем: данные исправлены осознанно
    }
};
