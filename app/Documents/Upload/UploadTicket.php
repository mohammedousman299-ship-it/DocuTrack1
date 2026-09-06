<?php

declare(strict_types=1);

namespace App\Documents\Upload;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Autorisation d'envoi direct vers le stockage objet (§3.3).
 *
 * L'image ne transite pas par le conteneur PHP : le navigateur écrit
 * directement dans le stockage au moyen d'une URL pré-signée. Cela déplace
 * deux responsabilités sur le serveur, et c'est tout l'objet de cette classe.
 *
 * 1. LA CLÉ D'OBJET EST GÉNÉRÉE PAR LE SERVEUR, jamais choisie par le client.
 *    Une clé fournie par le client permettrait d'écraser l'objet d'autrui ou
 *    de déposer hors du préfixe attendu.
 *
 * 2. LE TICKET LIE LA CLÉ À SON DEMANDEUR. Sans ce lien, un compte pourrait
 *    revendiquer à la soumission une clé envoyée par quelqu'un d'autre, et
 *    rattacher l'image d'un tiers à son propre signalement.
 *
 * Le ticket est de courte durée : une URL d'écriture qui traîne est une
 * surface d'attaque, et le parcours Trouveur se déroule en quelques minutes.
 */
final readonly class UploadTicket
{
    public const LIFETIME_MINUTES = 15;

    private function __construct(
        public string $objectKey,
        public string $uploadUrl,
    ) {}

    public static function issue(User $user): self
    {
        // Identifiant non devinable : une clé prévisible annulerait le
        // bénéfice du bucket privé (M-12).
        $objectKey = 'documents/'.Str::uuid()->toString().'.jpg';

        $url = Storage::disk('s3')->temporaryUploadUrl(
            $objectKey,
            now()->addMinutes(self::LIFETIME_MINUTES),
        )['url'];

        Cache::put(
            self::cacheKey($objectKey),
            $user->id,
            now()->addMinutes(self::LIFETIME_MINUTES),
        );

        return new self($objectKey, $url);
    }

    /** Le demandeur du ticket est-il bien celui qui soumet la clé ? */
    public static function belongsTo(string $objectKey, User $user): bool
    {
        return Cache::get(self::cacheKey($objectKey)) === $user->id;
    }

    /** Consomme le ticket : une clé ne sert qu'une fois. */
    public static function consume(string $objectKey): void
    {
        Cache::forget(self::cacheKey($objectKey));
    }

    private static function cacheKey(string $objectKey): string
    {
        return 'upload-ticket:'.hash('sha256', $objectKey);
    }
}
