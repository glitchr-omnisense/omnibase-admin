# Vendored assets

`jquery.min.js`, `core.js`, `transparent.js`/`transparent.css`, `sticky-sortable.js` and
`graph.js`/`layout.js`/`graph.css` are plain, unbundled copies — this package has no Encore/webpack build step
of its own. They are loaded as ordinary `<script>`/`<link>` tags so the
admin's own in-page navigation uses the exact same SPA engine as the rest of
the product and the website-in-website overlay, instead of a second library
(Turbo/Hotwired).

To update after a `@glitchr/flowjs` release (the repository is glitchr-studio/flowjs; it succeeds
transparentjs, stickyjs and ajaxer; the package is on npm, `@glitchr/flowjs`, and sites take it from there). The navigation and the sortable need the library's core, loaded before them:

    cp <flowjs-repo>/src/js/core.js         public/js/core.js
    cp <flowjs-repo>/src/js/transparent.js  public/js/transparent.js
    cp <flowjs-repo>/src/js/sortable.js     public/js/sticky-sortable.js   # StickySortable, superadmin customize-mode only
    cp <flowjs-repo>/src/css/navigation.scss public/css/transparent.css
    # jquery.min.js: cp node_modules/jquery/dist/jquery.min.js public/js/jquery.min.js

Currently vendored at flowjs 1.2.1 (glitchr-studio/flowjs 72b6174dae4c72aeea4104378b4c8a9a23de34ba) / jquery 3.7.1.
On npm as well: core.js is the 1.2.1 file (its `Flow.version` only since 1.1.0); transparent.js is the 1.2.1 file - a page of the
site under a status other than 200 (a form refused under 422, a creation under 201, the site's own error page) is swapped in like
any other, without `Rescue mode.. called`; sticky-sortable.js (sortable.js) and transparent.css (navigation.scss) are unchanged
since 1.0.3. The back office loads neither sticky, nor stops, nor ajaxer, nor cookie (1.2.0's module).
The four files are the library's own, byte for byte (no line added): `core.js` says `Flow.version`,
`transparent.js` `Transparent.version`. A fix the back office needs goes into flowjs first, then is
copied here. `layout.html.twig` loads `core.js` first, then `transparent.js`; `sticky-sortable.js` (the
file name kept; it is flowjs's `sortable.js`) is loaded only for `ROLE_SUPERADMIN`
(see `layout.html.twig`'s topbar block) - moderators/admins never download it.

To update after a `@glitchr/graphjs` release (github.com/glitchr-studio/graphjs;
a layered directed graph that is dragged and zoomed - HTML nodes, SVG edges,
no dependency, no build step). It is two ES modules and a stylesheet, copied
as they are; `graph.js` imports `./layout.js`, so the two stay side by side
under these names:

    cp <graphjs-repo>/src/js/graph.js   public/js/graph.js
    cp <graphjs-repo>/src/js/layout.js  public/js/layout.js
    cp <graphjs-repo>/src/css/index.css public/css/graph.css

Currently vendored at graphjs 1.0.0 (commit 226ddfe). Loaded on every admin
page by `layout.html.twig`, next to transparent.js: `<link>` for the
stylesheet, `<script type="module">` for `graph.js` (deferred). It starts every
`[data-graph]` of the page, and again after each in-admin navigation (it
listens to `transparent:load`); `window.Graph` is the class. See
`docs/graphs.md`.

IMPORTANT: this is not the only copy in this app. The public site (host
page) consumes `@glitchr/flowjs` via `assets/app-defer.js` from
`node_modules/@glitchr/flowjs`, installed from npm. Both copies must be updated
together or the host and the admin overlay run different versions of the
SAME library at the same time - this caused real, hard-to-diagnose bugs
(missing loading spinner, no close fade, iframe background bleed-through)
because the half that runs on the host page silently lagged behind.
