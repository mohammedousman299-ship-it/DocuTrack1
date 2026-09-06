/**
 * Préparation et envoi direct d'une image de document.
 *
 * Trois responsabilités, dans cet ordre :
 *
 * 1. RÉENCODAGE — l'image est redessinée dans un canvas puis réexportée. Ce
 *    procédé reconstruit le fichier à partir de ses seuls pixels : toutes les
 *    métadonnées disparaissent, y compris les coordonnées GPS qu'une photo de
 *    pièce d'identité embarque presque toujours.
 * 2. REDIMENSIONNEMENT — l'image est ramenée à une taille lisible pour
 *    l'administrateur, sans plus. Envoyer 8 mégapixels sur un réseau 3G
 *    échouerait, et rien dans la vérification n'en a besoin.
 * 3. ENVOI DIRECT — le fichier va au stockage objet sans passer par
 *    l'application (§3.3).
 *
 * Ce nettoyage n'est PAS une garantie : le serveur revérifie et renettoie
 * (D-029). Il évite simplement que des coordonnées quittent l'appareil.
 */

const MAX_DIMENSION = 1600;
const QUALITY = 0.82;

/** Redessine l'image dans un canvas et la réexporte, sans métadonnées. */
export async function prepareImage(file) {
    const bitmap = await createImageBitmap(file);

    let { width, height } = bitmap;
    const scale = Math.min(1, MAX_DIMENSION / Math.max(width, height));
    width = Math.round(width * scale);
    height = Math.round(height * scale);

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    context.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', QUALITY)
    );

    if (!blob) {
        throw new Error("L'image n'a pas pu être préparée.");
    }

    return blob;
}

/** Demande une autorisation d'envoi au serveur. */
async function requestTicket() {
    const response = await fetch('/signalement/piece-jointe/ticket', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error("L'autorisation d'envoi a été refusée.");
    }

    return response.json();
}

/**
 * Prépare puis envoie une image. Renvoie la clé d'objet à joindre au
 * formulaire.
 */
export async function uploadDocumentImage(file, onProgress = () => {}) {
    onProgress('preparing');
    const blob = await prepareImage(file);

    onProgress('requesting');
    const ticket = await requestTicket();

    onProgress('uploading');
    const response = await fetch(ticket.upload_url, {
        method: 'PUT',
        body: blob,
        headers: { 'Content-Type': 'image/jpeg' },
    });

    if (!response.ok) {
        throw new Error("L'envoi de l'image a échoué.");
    }

    onProgress('done');

    return { objectKey: ticket.object_key, size: blob.size };
}

/**
 * Branchement automatique des champs d'envoi.
 *
 * ------------------------------------------------------------------------
 * POURQUOI CE CODE N'EST PAS UNE EXPRESSION ALPINE.
 *
 * L'application applique une CSP stricte, sans unsafe-eval. Livewire y répond
 * par une variante d'Alpine compatible CSP, dont l'évaluateur n'accepte que
 * des expressions SIMPLES : accès de propriété, appels courts, ternaires. Un
 * bloc multi-instructions avec fonctions fléchées et chaîne de promesses —
 * ce que demande un envoi de fichier — le fait échouer, et l'échec est
 * silencieux côté utilisateur.
 *
 * La logique vit donc dans ce module, chargé comme script externe, et se
 * branche par délégation d'évènement. Aucune expression complexe n'atteint
 * l'évaluateur Alpine.
 * ------------------------------------------------------------------------
 */
const MESSAGES = {
    preparing: "Préparation de l'image…",
    requesting: 'Autorisation…',
    uploading: 'Envoi en cours…',
    done: 'Photo envoyée.',
};

function setStatus(container, text, isError = false) {
    const node = container.querySelector('[data-upload-status]');
    if (!node) return;
    node.textContent = text;
    node.className = isError
        ? 'text-sm font-medium text-danger-700'
        : 'text-sm font-medium text-slate-700';
}

async function handleFileSelection(input) {
    const container = input.closest('[data-upload-container]');
    const file = input.files && input.files[0];
    if (!file || !container) return;

    // Le composant Livewire propriétaire du champ, pour lui transmettre la clé.
    // La remontée est faite à la main plutôt qu'avec closest() : le nom
    // d'attribut « wire:id » contient un deux-points, que les sélecteurs CSS
    // n'acceptent pas dans un nom d'attribut, même échappé.
    let root = input;
    while (root && !root.hasAttribute('wire:id')) {
        root = root.parentElement;
    }
    const component = root ? window.Livewire.find(root.getAttribute('wire:id')) : null;

    try {
        const result = await uploadDocumentImage(file, (state) =>
            setStatus(container, MESSAGES[state] || '')
        );
        if (component) {
            component.set(input.dataset.uploadTarget || 'objectKey', result.objectKey);
        }
        setStatus(container, MESSAGES.done);
    } catch (error) {
        setStatus(container, error.message, true);
    }
}

// Délégation : le champ peut apparaître après un rendu Livewire.
document.addEventListener('change', (event) => {
    const input = event.target;
    if (input instanceof HTMLInputElement && input.hasAttribute('data-upload-input')) {
        handleFileSelection(input);
    }
});

window.docutrack = window.docutrack || {};
window.docutrack.uploadDocumentImage = uploadDocumentImage;
window.docutrack.prepareImage = prepareImage;
