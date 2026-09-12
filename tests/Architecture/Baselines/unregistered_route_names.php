<?php

// route('name') calls whose name is registered by no route.
// Each throws RouteNotFoundException the moment its call site is reached.
// Both sit in code nothing currently calls — verified, not assumed — so they
// are latent rather than live. Baseline may only shrink; never add to it.
// Baseline count: 2

return [
    // (F5 deleted RecitationPracticeController, which held the other two
    // entries: it read the Qur'an dataset, was routed nowhere, and the engine
    // covers recitation through `teach.recitations.*`.)

    // EventRegistration's QR url builder. Nothing calls it and the model has
    // no $appends, so it never fires during serialisation. Fixing it means
    // adding a public QR route — a feature, not a repair.
    'public.events.qr <- app/Domains/Website/Models/EventRegistration.php',

    // MediaGallery::getUrlAttribute(). Same shape: an accessor nothing reads,
    // and no $appends, so JSON serialisation does not trigger it. Needs a
    // gallery show route to exist before this can be anything but broken.
    'media-galleries.show <- app/Domains/Media/Models/MediaGallery.php',
];
