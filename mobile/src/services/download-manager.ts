import { Capacitor } from '@capacitor/core';
import { FileTransfer } from '@capacitor/file-transfer';
import { FileViewer } from '@capacitor/file-viewer';
import { Directory, Filesystem } from '@capacitor/filesystem';
import { Preferences } from '@capacitor/preferences';
import { buildIssuePdfUrl, buildPdfUrl } from './ojs-api';
import { sanitizeFilename, upsertDownloadIndex, type StoredDownloadIndexItem } from './download-helpers';
import type { ArticleDetail, IssueSummary } from 'src/types/ojs';

const DOWNLOAD_INDEX_KEY = 'unitepc-download-index';
const DOWNLOAD_FOLDER = 'revistas-unitepc';

export type DownloadRecord = StoredDownloadIndexItem;
export type DownloadDocumentType = DownloadRecord['documentType'];

interface DownloadablePayload {
  documentType: DownloadDocumentType;
  documentId: string;
  title: string;
  filename: string;
  downloadUrl: string;
}

function normalizeLegacyEntry(entry: Record<string, unknown>): DownloadRecord | null {
  const documentType = entry.documentType === 'issue' ? 'issue' : 'article';
  const documentId = typeof entry.documentId === 'string'
    ? entry.documentId
    : typeof entry.articleId === 'string'
      ? entry.articleId
      : null;

  if (!documentId || typeof entry.title !== 'string' || typeof entry.filename !== 'string' || typeof entry.path !== 'string' || typeof entry.uri !== 'string' || typeof entry.downloadedAt !== 'string') {
    return null;
  }

  return {
    documentType,
    documentId,
    title: entry.title,
    filename: entry.filename,
    path: entry.path,
    uri: entry.uri,
    downloadedAt: entry.downloadedAt,
  };
}

async function readIndex(): Promise<DownloadRecord[]> {
  const { value } = await Preferences.get({ key: DOWNLOAD_INDEX_KEY });

  if (!value) {
    return [];
  }

  try {
    const parsed = JSON.parse(value) as Record<string, unknown>[];
    return Array.isArray(parsed) ? parsed.map(normalizeLegacyEntry).filter((entry): entry is DownloadRecord => entry !== null) : [];
  } catch {
    return [];
  }
}

async function writeIndex(entries: DownloadRecord[]) {
  await Preferences.set({
    key: DOWNLOAD_INDEX_KEY,
    value: JSON.stringify(entries),
  });
}

async function fileExists(path: string) {
  try {
    await Filesystem.stat({
      directory: Directory.Documents,
      path,
    });

    return true;
  } catch {
    return false;
  }
}

async function ensureFilesystemPermissions() {
  try {
    const permissions = await Filesystem.checkPermissions();

    if (permissions.publicStorage !== 'granted') {
      await Filesystem.requestPermissions();
    }
  } catch {
    // iOS and web do not always expose the same permission shape.
  }
}

export async function reconcileDownloads(): Promise<DownloadRecord[]> {
  const entries = await readIndex();

  if (!Capacitor.isNativePlatform()) {
    return entries;
  }

  const validEntries: DownloadRecord[] = [];

  for (const entry of entries) {
    if (await fileExists(entry.path)) {
      validEntries.push(entry);
    }
  }

  if (validEntries.length !== entries.length) {
    await writeIndex(validEntries);
  }

  return validEntries;
}

export async function findDownload(documentType: DownloadDocumentType, documentId: string): Promise<DownloadRecord | null> {
  const downloads = await reconcileDownloads();
  return downloads.find((entry) => entry.documentType === documentType && entry.documentId === documentId) || null;
}

export async function findDownloadByArticleId(articleId: string): Promise<DownloadRecord | null> {
  return findDownload('article', articleId);
}

export async function findDownloadByIssueId(issueId: string): Promise<DownloadRecord | null> {
  return findDownload('issue', issueId);
}

async function ensureDownloaded(payload: DownloadablePayload): Promise<DownloadRecord> {
  const existing = await findDownload(payload.documentType, payload.documentId);

  if (existing) {
    return existing;
  }

  if (!Capacitor.isNativePlatform()) {
    window.open(payload.downloadUrl, '_blank', 'noopener,noreferrer');

    return {
      documentType: payload.documentType,
      documentId: payload.documentId,
      title: payload.title,
      filename: payload.filename,
      path: payload.downloadUrl,
      uri: payload.downloadUrl,
      downloadedAt: new Date().toISOString(),
    };
  }

  await ensureFilesystemPermissions();

  const filename = sanitizeFilename(payload.filename || `${payload.documentId}.pdf`);
  const path = `${DOWNLOAD_FOLDER}/${filename}`;

  await Filesystem.mkdir({
    directory: Directory.Documents,
    path: DOWNLOAD_FOLDER,
    recursive: true,
  });

  const fileUri = await Filesystem.getUri({
    directory: Directory.Documents,
    path,
  });

  await FileTransfer.downloadFile({
    url: payload.downloadUrl,
    path: fileUri.uri,
    progress: false,
  });

  const nextRecord: DownloadRecord = {
    documentType: payload.documentType,
    documentId: payload.documentId,
    title: payload.title,
    filename,
    path,
    uri: fileUri.uri,
    downloadedAt: new Date().toISOString(),
  };

  const nextIndex = upsertDownloadIndex(await readIndex(), nextRecord);
  await writeIndex(nextIndex);

  return nextRecord;
}

export async function ensureArticleDownloaded(article: ArticleDetail): Promise<DownloadRecord> {
  if (!article.pdf) {
    throw new Error('Este artículo no tiene un PDF descargable.');
  }

  return ensureDownloaded({
    documentType: 'article',
    documentId: article.id,
    title: article.title,
    filename: article.pdf.filename,
    downloadUrl: buildPdfUrl(article.id, 'attachment'),
  });
}

export async function ensureIssueDownloaded(issue: Pick<IssueSummary, 'id' | 'title' | 'pdf'>): Promise<DownloadRecord> {
  if (!issue.pdf) {
    throw new Error('Este número no tiene un PDF completo descargable.');
  }

  return ensureDownloaded({
    documentType: 'issue',
    documentId: issue.id,
    title: issue.title,
    filename: issue.pdf.filename,
    downloadUrl: buildIssuePdfUrl(issue.id, 'attachment'),
  });
}

export async function openDownloadedFile(download: DownloadRecord) {
  if (!Capacitor.isNativePlatform()) {
    window.open(download.uri, '_blank', 'noopener,noreferrer');
    return;
  }

  await FileViewer.openDocumentFromLocalPath({
    path: download.uri,
  });
}

export async function deleteDownload(documentType: DownloadDocumentType, documentId: string): Promise<DownloadRecord[]> {
  const downloads = await readIndex();
  const target = downloads.find((entry) => entry.documentType === documentType && entry.documentId === documentId);

  if (target && Capacitor.isNativePlatform()) {
    await Filesystem.deleteFile({
      directory: Directory.Documents,
      path: target.path,
    }).catch(() => undefined);
  }

  const remaining = downloads.filter((entry) => !(entry.documentType === documentType && entry.documentId === documentId));
  await writeIndex(remaining);

  return reconcileDownloads();
}
