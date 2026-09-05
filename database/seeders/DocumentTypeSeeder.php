<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catégories de documents.
 *
 * Reprises du prototype audité, seule partie de son contenu conservée comme
 * spécification (docs/AUDIT_PROTOTYPE.md §4).
 *
 * Les longueurs de numéro sont volontairement LARGES : les formats réels des
 * documents camerounais ne sont pas connus et ne seront pas inventés (D-012).
 * Une validation trop stricte rejetterait des numéros valides, créant une
 * impasse pour l'utilisateur — ce que §9.1 interdit.
 */
final class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $types = [
            // sensitivity 'high' => revue humaine obligatoire avant restitution.
            ['national_id', 'Carte nationale d\'identité (CNI)', 'National Identity Card', 'high'],
            ['passport', 'Passeport ordinaire', 'Ordinary Passport', 'high'],
            ['driving_licence', 'Permis de conduire', 'Driving Licence', 'high'],
            ['identity_attestation', 'Attestation d\'identité', 'Identity Attestation', 'high'],
            ['birth_certificate', 'Acte de naissance', 'Birth Certificate', 'standard'],
            ['health_insurance', 'Carte CNPS', 'National Health Insurance Card', 'standard'],
            ['academic_diploma', 'Diplôme (BEPC, BAC, licence)', 'Academic Diploma', 'standard'],
            ['student_card', 'Carte d\'étudiant', 'Student Card', 'standard'],
        ];

        foreach ($types as [$code, $labelFr, $labelEn, $sensitivity]) {
            DB::table('document_types')->updateOrInsert(
                ['code' => $code],
                [
                    'label_fr' => $labelFr,
                    'label_en' => $labelEn,
                    'sensitivity' => $sensitivity,
                    'number_min_length' => 4,
                    'number_max_length' => 30,
                    'number_alphabet' => 'A-Z0-9',
                    'retention_days' => 180, // D-010
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
