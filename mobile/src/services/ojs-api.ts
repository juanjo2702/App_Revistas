import type {
  ArticleDetail,
  ArticleSummary,
  DevicePreferencePayload,
  DevicePreferenceState,
  DeviceRegistrationPayload,
  HealthSnapshot,
  IssueSummary,
  JournalSummary,
  SearchResult,
  SourceSummary,
} from 'src/types/ojs';

interface CollectionResponse<T> {
  data: T[];
}

interface ItemResponse<T> {
  data: T;
}

export function resolveBridgeBaseUrl(rawBaseUrl = import.meta.env.VITE_API_BASE_URL): string {
  const fallback = 'http://10.0.2.2:8000/api/v1';

  return (rawBaseUrl || fallback).replace(/\/+$/, '');
}

function resolveApiRoot() {
  return resolveBridgeBaseUrl().replace(/\/api\/v1$/, '');
}

export function buildBridgeUrl(path: string, query?: Record<string, string | number | undefined | null>): string {
  const url = new URL(`${resolveBridgeBaseUrl()}${path}`);

  Object.entries(query || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });

  return url.toString();
}

function normalizeNetworkError(error: unknown): Error {
  if (error instanceof Error && error.message === 'Failed to fetch') {
    return new Error(
      'No pudimos conectarnos en este momento. Verifica tu conexión a internet e inténtalo nuevamente.',
    );
  }

  if (error instanceof Error) {
    return error;
  }

  return new Error('No pudimos completar la solicitud en este momento.');
}

async function requestJson<T>(url: string, init?: RequestInit): Promise<T> {
  let response: Response;

  try {
    response = await fetch(url, {
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(init?.headers || {}),
      },
      ...init,
    });
  } catch (error) {
    throw normalizeNetworkError(error);
  }

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    const validationErrors = payload && typeof payload === 'object' && 'errors' in payload
      ? payload.errors as Record<string, string[] | undefined>
      : null;
    const firstValidationMessage = validationErrors
      ? Object.values(validationErrors).flat().find((message) => typeof message === 'string' && message.trim() !== '')
      : null;
    const message = typeof firstValidationMessage === 'string'
      ? firstValidationMessage
      : typeof payload.message === 'string'
        ? payload.message
        : 'No pudimos completar tu solicitud en este momento.';

    throw new Error(message);
  }

  return response.json() as Promise<T>;
}

export async function getSources(): Promise<SourceSummary[]> {
  const payload = await requestJson<CollectionResponse<SourceSummary>>(buildBridgeUrl('/sources'));
  return payload.data;
}

export async function getJournals(source: string): Promise<JournalSummary[]> {
  const payload = await requestJson<CollectionResponse<JournalSummary>>(buildBridgeUrl('/journals', { source }));
  return payload.data;
}

export async function getIssues(journalId: string): Promise<IssueSummary[]> {
  const payload = await requestJson<CollectionResponse<IssueSummary>>(
    buildBridgeUrl(`/journals/${encodeURIComponent(journalId)}/issues`),
  );

  return payload.data;
}

export async function getArticles(issueId: string): Promise<ArticleSummary[]> {
  const payload = await requestJson<CollectionResponse<ArticleSummary>>(
    buildBridgeUrl(`/issues/${encodeURIComponent(issueId)}/articles`),
  );

  return payload.data;
}

export async function getArticle(articleId: string): Promise<ArticleDetail> {
  const payload = await requestJson<ItemResponse<ArticleDetail>>(
    buildBridgeUrl(`/articles/${encodeURIComponent(articleId)}`),
  );

  return payload.data;
}

export async function searchCatalog(query: string, source?: string | null, year?: number | null): Promise<SearchResult[]> {
  const payload = await requestJson<CollectionResponse<SearchResult>>(buildBridgeUrl('/search', {
    q: query,
    source,
    year,
  }));

  return payload.data;
}

export async function registerDevice(payload: DeviceRegistrationPayload) {
  const response = await requestJson<ItemResponse<{
    deviceId: string;
    notificationsEnabled: boolean;
    pushConfigured: boolean;
  }>>(buildBridgeUrl('/devices/register'), {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  return response.data;
}

export async function getDevicePreferences(deviceId: string): Promise<DevicePreferenceState> {
  const payload = await requestJson<ItemResponse<DevicePreferenceState>>(
    buildBridgeUrl(`/devices/preferences/${encodeURIComponent(deviceId)}`),
  );

  return payload.data;
}

export async function updateDevicePreferences(payload: DevicePreferencePayload): Promise<DevicePreferenceState> {
  const response = await requestJson<ItemResponse<DevicePreferenceState>>(buildBridgeUrl('/devices/preferences'), {
    method: 'PUT',
    body: JSON.stringify(payload),
  });

  return response.data;
}

export async function getHealthSnapshot(): Promise<HealthSnapshot> {
  return requestJson<HealthSnapshot>(`${resolveApiRoot()}/api/health`);
}

export function buildPdfUrl(articleId: string, disposition: 'inline' | 'attachment' = 'inline') {
  return buildBridgeUrl(`/articles/${encodeURIComponent(articleId)}/pdf`, { disposition });
}

export function buildIssuePdfUrl(issueId: string, disposition: 'inline' | 'attachment' = 'inline') {
  return buildBridgeUrl(`/issues/${encodeURIComponent(issueId)}/pdf`, { disposition });
}
