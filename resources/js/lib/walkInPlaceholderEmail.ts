/**
 * Walk-in clients without an email get a synthetic address (WalkInClientController).
 * Tables should show blank instead of that placeholder.
 */
const WALK_IN_PLACEHOLDER_EMAIL_SUFFIX = '@no-email.walkin.local';

export function displayEmailUnlessWalkInPlaceholder(email: string | null | undefined): string {
    if (!email) return '';
    return email.endsWith(WALK_IN_PLACEHOLDER_EMAIL_SUFFIX) ? '' : email;
}
