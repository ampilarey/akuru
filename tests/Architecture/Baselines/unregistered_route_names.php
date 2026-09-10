<?php

// route('name') calls whose name is registered by no route.
// Each throws RouteNotFoundException the moment its call site is reached.
// All four sit in code nothing currently calls — verified, not assumed — so
// they are latent rather than live. Baseline may only shrink; never add to it.
// Baseline count: 4

return [
    // RecitationPracticeController is not routed at all — no entry in
    // app/Domains/Hifz/routes.php or routes/. Dead controller. Hifz is frozen
    // (rule 7), and deleting it is a scope change, not a route change.
    'recitation-practices.index <- app/Domains/Hifz/Http/Controllers/RecitationPracticeController.php',
    'recitation-practices.show <- app/Domains/Hifz/Http/Controllers/RecitationPracticeController.php',

    // EventRegistration's QR url builder. Nothing calls it and the model has
    // no $appends, so it never fires during serialisation. Fixing it means
    // adding a public QR route — a feature, not a repair.
    'public.events.qr <- app/Domains/Website/Models/EventRegistration.php',

    // MediaGallery::getUrlAttribute(). Same shape: an accessor nothing reads,
    // and no $appends, so JSON serialisation does not trigger it. Needs a
    // gallery show route to exist before this can be anything but broken.
    'media-galleries.show <- app/Domains/Media/Models/MediaGallery.php',
];
