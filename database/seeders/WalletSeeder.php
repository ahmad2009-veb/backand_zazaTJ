<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Wallet;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $wallets = [
            [
                'name' => 'Наличные',
                'logo' => null,
                'is_available' => true,
            ],
            [
                'name' => 'Алиф Банк',
                'logo' => 'assets/wallet_icons/alif.jpg',
                'is_available' => true,
            ],
            [
                'name' => 'Амонатбанк',
                'logo' => 'assets/wallet_icons/amonatbonk.jpg',
                'is_available' => true,
            ],
            [
                'name' => 'ДС Кошелек',
                'logo' => 'assets/wallet_icons/dcwallet.jpg',
                'is_available' => true,
            ],
            [
                'name' => 'Эсхата Банк',
                'logo' => 'assets/wallet_icons/eskhata.jpg',
                'is_available' => true,
            ],
            [
                'name' => 'Хумо',
                'logo' => 'assets/wallet_icons/khumo.jpg',
                'is_available' => true,
            ],
        ];

        foreach ($wallets as $w) {
            Wallet::updateOrCreate(
                ['name' => $w['name']],
                ['logo' => $w['logo'], 'is_available' => $w['is_available']]
            );
        }
    }
}

