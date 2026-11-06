// Column roles derived from the events file header, so nothing here hard-codes
// the 28 columns of one account's export. The header of
// input/agentConversationEvents_nnnnn.csv is the authority (CLAUDE.md).

export const REQUIRED_EVENT_COLUMNS = [
  '_id', 'event_code', 'event_date', 'event_path', 'event_element', 'app_code',
  'Browser', 'Device', 'OS', 'Platform', 'IP',
  'audio_transcript', 'audio_recording', 'tokens_used', 'audio_time', 'data', 'acctID',
];

// Event-block columns, by name. Every other column except acctID is the contact block.
export const EVENT_BLOCK_COLUMNS = REQUIRED_EVENT_COLUMNS.filter((c) => c !== '_id' && c !== 'acctID');

export const FINGERPRINT_COLUMNS = ['Browser', 'Device', 'OS', 'Platform'];
export const COMPLETION_PAYLOAD_COLUMNS = ['audio_transcript', 'audio_recording', 'tokens_used', 'audio_time'];

export function assertEventHeader(header) {
  const missing = REQUIRED_EVENT_COLUMNS.filter((c) => !header.includes(c));
  if (missing.length) throw new Error(`Events file is missing columns: ${missing.join(', ')}`);
  const lower = header.map((c) => c.toLowerCase());
  const clash = header.filter((c, i) => lower.indexOf(lower[i]) !== i);
  if (clash.length) throw new Error(`Events file has columns that differ only by case: ${clash.join(', ')}`);
}

// Output header: the events file header, same columns and order, all lowercase (CLAUDE.md).
export function outputHeader(eventHeader) {
  return eventHeader.map((c) => c.toLowerCase());
}

// Contact block = every column that is not an event column or acctID (PURL, email,
// names, address, _id ...). Chosen by name, so column order in either file does not matter.
export function contactBlockColumns(header) {
  return header.filter((c) => c !== 'acctID' && !EVENT_BLOCK_COLUMNS.includes(c));
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
