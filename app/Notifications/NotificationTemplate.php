<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Gabarits de notification, avec leurs paramètres AUTORISÉS.
 *
 * ------------------------------------------------------------------------
 * Contrôle central de la §7 et de THREAT_MODEL.md M-10.
 *
 * Une notification ne doit JAMAIS contenir de donnée de niveau N2 ou N3 : ni
 * numéro de document même partiel, ni nom complet, ni lieu précis, ni image.
 * Un SMS s'affiche sur un écran verrouillé, dans un lieu public, sur un
 * téléphone parfois partagé ; un e-mail transite par un tiers.
 *
 * Cette règle n'est pas une consigne de rédaction : chaque gabarit déclare ici
 * la liste EXHAUSTIVE des paramètres qu'il accepte, et le répartiteur refuse
 * tout le reste. Ajouter une donnée sensible à une notification demanderait de
 * modifier ce fichier — ce qui rend l'infraction visible en revue de code, au
 * lieu d'être noyée dans un appel.
 * ------------------------------------------------------------------------
 */
enum NotificationTemplate: string
{
    case PhoneVerificationCode = 'phone_verification_code';
    case EmailVerificationLink = 'email_verification_link';
    case PasswordReset = 'password_reset';
    case MatchFound = 'match_found';
    case SearchCompleted = 'search_completed';
    case SearchNoResult = 'search_no_result';
    case ClaimDecision = 'claim_decision';

    /**
     * Paramètres autorisés — liste exhaustive et volontairement pauvre.
     *
     * `given_name` est le PREMIER TOKEN du nom du compte, jamais le nom
     * complet : il personnalise le message sans révéler d'identité à qui lit
     * l'écran par-dessus l'épaule.
     *
     * @return list<string>
     */
    public function allowedParameters(): array
    {
        return match ($this) {
            self::PhoneVerificationCode => ['code'],
            self::EmailVerificationLink => ['given_name', 'url'],
            self::PasswordReset => ['url'],
            // Volontairement SANS aucun paramètre : le message annonce
            // l'existence d'une correspondance possible et invite à se
            // connecter. Rien du document, rien du signalement.
            self::MatchFound => [],
            self::SearchCompleted => [],
            self::SearchNoResult => [],
            self::ClaimDecision => [],
        };
    }

    /**
     * Un gabarit sensible ne part jamais par SMS.
     *
     * Le SMS n'est pas confidentiel. Les liens de réinitialisation et de
     * vérification d'adresse n'y ont donc pas leur place.
     */
    public function allowsSms(): bool
    {
        return match ($this) {
            self::EmailVerificationLink, self::PasswordReset => false,
            default => true,
        };
    }

    public function translationKey(): string
    {
        return 'notifications.'.$this->value;
    }
}
