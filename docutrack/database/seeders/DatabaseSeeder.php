<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FoundDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'National Identity Card (CNI)',
            'Cameroonian Ordinary Passport',
            "Cameroonian Driver's License",
            "Attestation d'Identité",
            'Birth Certificate / Acte de Naissance',
            'National Health Insurance / CNPS Card',
            'Academic Diploma / BEPC / BAC / Degree',
            'Cameroonian Student Card',
        ] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        User::firstOrCreate(['email' => 'admin@docutrack.cm'], [
            'name' => 'System Admin', 'phone' => '+237600000000', 'password' => 'password', 'role' => 'admin',
        ]);
        $john = User::firstOrCreate(['email' => 'john@example.com'], [
            'name' => 'John Doe', 'phone' => '+237611111111', 'password' => 'password',
        ]);

        FoundDocument::firstOrCreate(['doc_number' => '102938475'], [
            'user_id' => $john->id,
            'category_id' => Category::where('name', 'National Identity Card (CNI)')->value('id'),
            'owner_name' => "Samuel Eto'o",
            'location' => 'Yaoundé Central Station',
            'deposit_point' => 'Police Station 1st District, Yaoundé',
            'context' => 'Found inside a wallet near ticket counter',
        ]);
    }
}
