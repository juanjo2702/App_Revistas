import { describe, expect, it } from 'vitest';
import { buildBridgeUrl, buildIssuePdfUrl, resolveBridgeBaseUrl } from '../ojs-api';

describe('ojs-api helpers', () => {
  it('normalizes the configured base URL', () => {
    expect(resolveBridgeBaseUrl('https://api.example.com/api/v1/')).toBe('https://api.example.com/api/v1');
  });

  it('builds bridge URLs with query params', () => {
    expect(buildBridgeUrl('/journals', { source: 'investigacion' })).toContain('/journals?source=investigacion');
  });

  it('builds issue pdf URLs', () => {
    expect(buildIssuePdfUrl('g-news:revista:2')).toContain('/issues/g-news%3Arevista%3A2/pdf?disposition=inline');
  });
});
