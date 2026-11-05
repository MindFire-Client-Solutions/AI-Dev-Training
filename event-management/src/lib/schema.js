// Column roles derived from the events file header, so nothing here hard-codes
// the 28 columns of one account's export. The header of
// input/agentConversationEvents_nnnnn.csv is the authority (CLAUDE.md).

export const REQUIRED_EVENT_COLUMNS = [
  '_id', 'event_code', 'event_date', 'event_path', 'event_element', 'app_code',
  'Browser', 'Device', 'OS', 'Platform', 'IP',
  'audio_transcript', 'audio_recording', 'tokens_used', 'audio_time', 'data', 'acctID',
];

export const FINGERPRINT_COLUMNS = ['Browser', 'Device', 'OS', 'Platform'];
export const COMPLETION_PAYLOAD_COLUMNS = ['audio_transcript', 'audio_recording', 'tokens_used', 'audio_time'];

export function assertEventHeader(header) {
  const missing = REQUIRED_EVENT_COLUMNS.filter((c) => !header.includes(c));
  if (missing.length) throw new Error(`Events file is missing columns: ${missing.join(', ')}`);
  if (header[header.length - 1] !== 'acctID') throw new Error('Events file: acctID must be the last column');
}

// Contact block = every column before event_code (PURL, email, names, address, _id ...).
export function contactBlockColumns(header) {
  return header.slice(0, header.indexOf('event_code'));
}

// Map each contact-block column to the matching contacts.csv column, case-insensitively
// (contacts.csv has `purl`, the export has `PURL`). null = absent, emitted empty.
export function contactColumnMap(eventHeader, contactsHeader) {
  const byLower = new Map(contactsHeader.map((c) => [c.toLowerCase(), c]));
  return Object.fromEntries(
    contactBlockColumns(eventHeader).map((col) => [col, byLower.get(col.toLowerCase()) ?? null]),
  );
}

export function contactBlockFor(contact, columnMap) {
  return Object.fromEntries(
    Object.entries(columnMap).map(([col, src]) => [col, src ? contact[src] ?? '' : '']),
  );
}

export function assertContactsHeader(header) {
  if (!header.includes('_id')) throw new Error('Contacts file is missing the _id column');
}

// agentConversationEvents_nnnnn.csv -> 'nnnnn', or null if the name does not follow the pattern.
export function accountFromFileName(name) {
  const m = /agentConversationEvents_(\d+)\.csv$/i.exec(name || '');
  return m ? m[1] : null;
}
