export interface SourceSummary {
  slug: string;
  name: string;
  driver: string;
  baseUrl: string;
  apiBaseUrl?: string | null;
}

export interface JournalSummary {
  id: string;
  source: string;
  remoteId: string;
  name: string;
  description?: string | null;
  issn?: string | null;
  url?: string | null;
  thumbnailUrl?: string | null;
}

export interface IssueSummary {
  id: string;
  journalId: string;
  remoteId: string;
  title: string;
  volume?: string | number | null;
  number?: string | number | null;
  year?: string | number | null;
  description?: string | null;
  publishedAt?: string | null;
  coverUrl?: string | null;
  url?: string | null;
  pdf?: PdfAsset | null;
}

export interface PdfAsset {
  url: string;
  mimeType: string;
  filename: string;
  downloadable: boolean;
}

export interface ArticleSummary {
  id: string;
  journalId: string;
  remoteId: string;
  issueId?: string | null;
  title: string;
  subtitle?: string | null;
  authors: string[];
  authorsString?: string | null;
  abstract?: string | null;
  keywords: string[];
  doi?: string | null;
  pages?: string | null;
  publishedAt?: string | null;
  url?: string | null;
  pdf?: PdfAsset | null;
}

export interface ArticleDetail extends ArticleSummary {
  citations: string[];
  licenseUrl?: string | null;
  references: string[];
  section?: string | null;
}

export interface SearchResult extends ArticleSummary {
  source?: string | null;
  journalName?: string | null;
  issueTitle?: string | null;
  sourceName?: string | null;
  year?: string | number | null;
}

export interface DeviceRegistrationPayload {
  deviceId: string;
  platform: string;
  appVersion?: string | null;
  locale?: string | null;
  pushToken?: string | null;
  notificationsEnabled?: boolean;
  meta?: Record<string, unknown> | null;
}

export interface DevicePreferencePayload extends DeviceRegistrationPayload {
  followedSources: string[];
  followedJournals: string[];
  followedYears: number[];
}

export interface DevicePreferenceState {
  deviceId: string;
  platform?: string | null;
  appVersion?: string | null;
  locale?: string | null;
  notificationsEnabled: boolean;
  pushConfigured: boolean;
  followedSources: string[];
  followedJournals: string[];
  followedYears: number[];
  updatedAt?: string | null;
}

export interface HealthSnapshot {
  status: string;
  environment: string;
  time: string;
  catalog: {
    journals: number;
    issues: number;
    articles: number;
  };
  devices: {
    registered: number;
    pushEnabled: number;
  };
}
