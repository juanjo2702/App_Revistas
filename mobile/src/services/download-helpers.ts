export interface StoredDownloadIndexItem {
  documentType: 'article' | 'issue';
  documentId: string;
  title: string;
  filename: string;
  path: string;
  uri: string;
  downloadedAt: string;
}

export function sanitizeFilename(filename: string): string {
  const cleaned = filename
    .normalize('NFKD')
    .replace(/[^\w.-]+/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^-|-$/g, '')
    .toLowerCase();

  return cleaned.toLowerCase().endsWith('.pdf') ? cleaned : `${cleaned || 'documento'}.pdf`;
}

export function upsertDownloadIndex(
  entries: StoredDownloadIndexItem[],
  nextEntry: StoredDownloadIndexItem,
): StoredDownloadIndexItem[] {
  const withoutCurrent = entries.filter((entry) =>
    !(entry.documentType === nextEntry.documentType && entry.documentId === nextEntry.documentId));
  return [nextEntry, ...withoutCurrent].sort((left, right) => right.downloadedAt.localeCompare(left.downloadedAt));
}
