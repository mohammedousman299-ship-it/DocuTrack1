<?php

declare(strict_types=1);

/*
 | Gabarits de notification — CONTENU STRICTEMENT MINIMAL (§7, M-10).
 |
 | Aucun message ne contient de numéro de document même partiel, de nom
 | complet, de lieu précis ni d'image. Un SMS s'affiche sur un écran
 | verrouillé, dans un lieu public, sur un téléphone parfois partagé.
 |
 | Le vocabulaire est une exigence de conception : on écrit toujours
 | « correspondance possible », jamais « votre document a été retrouvé ».
 */

return [
    'phone_verification_code' => 'DocuTrack : votre code de vérification est :code. Il expire dans 10 minutes. Ne le communiquez à personne.',

    'email_verification_link' => 'Bonjour :given_name, confirmez votre adresse e-mail DocuTrack en suivant ce lien : :url',

    'password_reset' => 'Réinitialisation de votre mot de passe DocuTrack : :url. Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.',

    'match_found' => 'DocuTrack : une correspondance possible a été identifiée pour l\'une de vos déclarations. Connectez-vous pour en savoir plus.',

    'search_completed' => 'DocuTrack : votre recherche est terminée. Connectez-vous pour consulter le résultat.',

    'search_no_result' => 'DocuTrack : votre recherche n\'a donné aucun résultat pour le moment. Connectez-vous pour déclarer la perte et être prévenu plus tard.',

    'claim_decision' => 'DocuTrack : votre demande a été traitée. Connectez-vous pour en connaître le résultat.',
];
