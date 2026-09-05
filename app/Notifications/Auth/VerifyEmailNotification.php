<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Vérification d'adresse e-mail.
 *
 * ------------------------------------------------------------------------
 * RÉPARTITION DES DEUX SYSTÈMES DE NOTIFICATION, pour éviter la confusion :
 *
 * - Les notifications d'AUTHENTIFICATION (vérification d'adresse,
 *   réinitialisation de mot de passe) passent par le système de Laravel :
 *   elles reposent sur des URL signées que le framework produit et vérifie.
 * - Les notifications MÉTIER (correspondance, recherche terminée, code SMS)
 *   passent par NotificationDispatcher, qui porte la contrainte de contenu
 *   minimal, l'idempotence et le plafond par période (§7, M-10).
 *
 * Les deux aboutissent dans la même file, drainée par
 * POST /internal/queue/drain : il n'existe ni worker permanent ni scheduler en
 * processus sur la plateforme cible (§3.1).
 * ------------------------------------------------------------------------
 */
final class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        // Contenu minimal : le lien et rien d'autre. Aucune donnée métier
        // n'a sa place dans un message d'authentification.
        return (new MailMessage)
            ->subject(__('auth_notifications.verify_email.subject'))
            ->line(__('auth_notifications.verify_email.line'))
            ->action(__('auth_notifications.verify_email.action'), $url)
            ->line(__('auth_notifications.verify_email.ignore'));
    }
}
