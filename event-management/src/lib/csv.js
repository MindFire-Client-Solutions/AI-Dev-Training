// RFC 4180 CSV parsing and writing. Runs in Node and in the browser.
// Output matches the source export: minimal quoting, CRLF line endings,
// trailing CRLF after the last row.

export function parseCsv(text) {
  if (text.charCodeAt(0) === 0xfeff) text = text.slice(1);
  const rows = [];
  let row = [];
  let field = '';
  let quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"') {
        if (text[i + 1] === '"') { field += '"'; i++; } else quoted = false;
      } else field += c;
    } else if (c === '"') quoted = true;
    else if (c === ',') { row.push(field); field = ''; }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i++;
      row.push(field); rows.push(row); row = []; field = '';
    } else field += c;
  }
  if (quoted) throw new Error('CSV ends inside a quoted field');
  if (field !== '' || row.length) { row.push(field); rows.push(row); }
  // Drop fully empty trailing lines.
  while (rows.length && rows[rows.length - 1].every((f) => f === '')) rows.pop();
  if (!rows.length) throw new Error('CSV is empty');

  const header = rows[0];
  const records = rows.slice(1).map((r, i) => {
    if (r.length !== header.length) {
      throw new Error(`CSV row ${i + 2} has ${r.length} fields, header has ${header.length}`);
    }
    return Object.fromEntries(header.map((h, j) => [h, r[j]]));
  });
  return { header, records };
}

function quote(v) {
  const s = v == null ? '' : String(v);
  return /[",\r\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}

export function writeCsv(header, records) {
  const lines = [header.map(quote).join(',')];
  for (const r of records) lines.push(header.map((h) => quote(r[h])).join(','));
  return lines.join('\r\n') + '\r\n';
}
