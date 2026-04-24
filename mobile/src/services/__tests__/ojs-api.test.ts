import { afterEach, describe, expect, it, vi } from 'vitest';
import { buildBridgeUrl, buildIssuePdfUrl, resolveBridgeBaseUrl, updateDevicePreferences } from '../ojs-api';

describe('ojs-api helpers', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('normalizes the configured base URL', () => {
    expect(resolveBridgeBaseUrl('https://api.example.com/api/v1/')).toBe('https://api.example.com/api/v1');
  });

  it('builds bridge URLs with query params', () => {
    expect(buildBridgeUrl('/journals', { source: 'investigacion' })).toContain('/journals?source=investigacion');
  });

  it('builds issue pdf URLs', () => {
    expect(buildIssuePdfUrl('g-news:revista:2')).toContain('/issues/g-news%3Arevista%3A2/pdf?disposition=inline');
  });

  it('prefers the first validation error message from the backend', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: false,
      json: () => Promise.resolve({
        message: 'The followed sources field is required. (and 1 more error)',
        errors: {
          followedSources: ['Debes seleccionar al menos una colección.'],
          followedJournals: ['Debes seleccionar al menos una revista.'],
        },
      }),
    }));

    await expect(updateDevicePreferences({
      deviceId: '7c61e9b0-42d8-4f17-bc52-6f7d460bef62',
      platform: 'android',
      appVersion: '1.0',
      locale: 'es-BO',
      notificationsEnabled: true,
      followedSources: [],
      followedJournals: [],
      followedYears: [],
    })).rejects.toThrow('Debes seleccionar al menos una colección.');
  });
});
