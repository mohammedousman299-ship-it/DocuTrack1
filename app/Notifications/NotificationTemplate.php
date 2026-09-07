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
    /**
     * Message UNIQUE de fin de recherche (D-034).
     *
     * Il ne dit jamais si quelque chose a été trouvé. Deux messages distincts
     * révéleraient le résultat sur un écran verrouillé et rendraient à un
     * attaquant le signal binaire que la recherche différée avait précisément
     * pour but de supprimer : il lui suffirait de lire ses SMS au lieu
     * d'itérer sur le site, hors de portée des quotas et de la journalisation.
     */
    case SearchCompleted = 'search_completed';
    case ClaimDecision = 'claim_decision';
    /**
     * Refus de revue d'une déclaration à nom incohérent (D-036).
     *
     * Il existe parce que l'inverse — ne rien envoyer — laisserait quelqu'un
     * qui a déclaré pour un parent attendre indéfiniment une notification qui
     * ne viendra pas. Il ne dit NI le motif du refus, NI rien du document :
     * le motif se lit après connexion, pas sur un écran verrouillé.
     */
    case DeclarationRejected = 'declaration_rejected';

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
            self::ClaimDecision => [],
            self::DeclarationRejected => [],
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
