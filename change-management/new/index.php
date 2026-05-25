<?php
/**
 * /change-management/new/ — pretty URL for the "create change" action.
 *
 * The editor lives inside change-management/index.php as one of three
 * in-page views (list / detail / editor) sharing a lot of state and DOM
 * elements with the listing UI. Rather than duplicate ~300 lines of
 * editor markup and the wiring around it, this route 302-redirects to
 * ../?new=1. The JS over there reads the param, opens the editor on
 * load, and then history.replaceState's the URL back to
 * /change-management/new/ so the address bar stays clean.
 */
header('Location: ../?new=1');
exit;
