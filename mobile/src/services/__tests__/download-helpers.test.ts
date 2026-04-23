import { describe, expect, it } from 'vitest';
import { sanitizeFilename, upsertDownloadIndex } from '../download-helpers';

describe('download helpers', () => {
  it('sanitizes PDF filenames for filesystem storage', () => {
    expect(sanitizeFilename('Artículo Final 2026.pdf')).toBe('arti-culo-final-2026.pdf');
    expect(sanitizeFilename('mi archivo raro')).toBe('mi-archivo-raro.pdf');
  });

  it('reuses the newest entry per article in the download index', () => {
    const next = upsertDownloadIndex([
      {
        documentType: 'article',
        documentId: 'investigacion:10:4',
        title: 'Antiguo',
        filename: 'viejo.pdf',
        path: 'revistas/viejo.pdf',
        uri: 'file:///revistas/viejo.pdf',
        downloadedAt: '2026-04-20T10:00:00.000Z',
      },
    ], {
      documentType: 'article',
      documentId: 'investigacion:10:4',
      title: 'Nuevo',
      filename: 'nuevo.pdf',
      path: 'revistas/nuevo.pdf',
      uri: 'file:///revistas/nuevo.pdf',
      downloadedAt: '2026-04-21T10:00:00.000Z',
    });

    expect(next).toHaveLength(1);
    expect(next[0]?.filename).toBe('nuevo.pdf');
  });
});
