#!/usr/bin/env node

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';

// This server has one job: serve a chosen slide deck and a narrow shared alias
// for `reveal.js` out of `node_modules`. Deck files stay in `slides/<deck>/`,
// while shared library files come from `/__slides_vendor__/reveal.js/...`.

/**
 * Returns a content type for a file extension.
 *
 * @param {string} filePath
 *   The file path to inspect.
 *
 * @return {string}
 *   The response content type.
 */
function getContentType(filePath) {
  const extension = path.extname(filePath).toLowerCase();
  const contentTypes = {
    '.css': 'text/css; charset=utf-8',
    '.html': 'text/html; charset=utf-8',
    '.ico': 'image/x-icon',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.mjs': 'application/javascript; charset=utf-8',
    '.mp4': 'video/mp4',
    '.png': 'image/png',
    '.svg': 'image/svg+xml',
    '.txt': 'text/plain; charset=utf-8',
    '.wav': 'audio/wav',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
  };

  return contentTypes[extension] || 'application/octet-stream';
}

/**
 * Resolves and validates a request path within an allowed base directory.
 *
 * @param {string} baseDirectory
 *   The directory that may be served.
 * @param {string} requestPath
 *   The URL path relative to the base directory.
 * @param {boolean} preferIndex
 *   Whether "/" and directory paths should resolve to index.html.
 *
 * @return {string | null}
 *   The resolved file path or null if invalid.
 */
function resolveFilePath(baseDirectory, requestPath, preferIndex = false) {
  const sanitizedPath = requestPath.replace(/^\/+/, '');
  const relativePath = sanitizeRelativePath(sanitizedPath);

  if (relativePath === null) {
    return null;
  }

  let resolvedPath = path.resolve(baseDirectory, relativePath);

  // `path.resolve()` normalizes the path, so this check prevents requests such
  // as `../../somewhere-else` from escaping the allowed directory.
  if (!resolvedPath.startsWith(baseDirectory)) {
    return null;
  }

  if (preferIndex) {
    // Deck routes behave like a tiny static website: `/` should open the deck's
    // `index.html`, and a directory path should also fall back to `index.html`.
    if (requestPath === '/' || requestPath === '') {
      resolvedPath = path.resolve(baseDirectory, 'index.html');
    }
    else if (fs.existsSync(resolvedPath) && fs.statSync(resolvedPath).isDirectory()) {
      resolvedPath = path.resolve(resolvedPath, 'index.html');
    }
  }

  return resolvedPath;
}

/**
 * Removes unsafe path traversal segments from a relative path request.
 *
 * @param {string} relativePath
 *   The path to sanitize.
 *
 * @return {string | null}
 *   The normalized relative path or null if traversal is attempted.
 */
function sanitizeRelativePath(relativePath) {
  const normalizedPath = path.posix.normalize(`/${relativePath}`);

  if (normalizedPath.includes('..')) {
    return null;
  }

  return normalizedPath.replace(/^\/+/, '');
}

const [, , deckDirectory, revealDirectory, slidesDirectoryName] = process.argv;

// The Bash wrapper passes exactly three arguments:
// - the deck directory to serve
// - the installed `node_modules/reveal.js` directory
// - the human-friendly deck name for logging
if (!deckDirectory || !revealDirectory || !slidesDirectoryName) {
  console.error('Usage: node scripts/slides-server.mjs <deck-directory> <reveal-directory> <slides-directory>');
  process.exit(1);
}

const resolvedDeckDirectory = path.resolve(deckDirectory);
const resolvedRevealDirectory = path.resolve(revealDirectory);
const vendorAlias = '/__slides_vendor__/reveal.js/';

const server = http.createServer((request, response) => {
  const requestUrl = new URL(request.url || '/', 'http://127.0.0.1');
  let filePath = null;

  // Requests under the shared alias are served from `node_modules/reveal.js`.
  // Everything else is treated as a deck-local file request.
  if (requestUrl.pathname.startsWith(vendorAlias)) {
    const vendorPath = requestUrl.pathname.slice(vendorAlias.length);
    filePath = resolveFilePath(resolvedRevealDirectory, vendorPath);
  }
  else {
    filePath = resolveFilePath(resolvedDeckDirectory, requestUrl.pathname, true);
  }

  if (!filePath) {
    // A bad request here usually means path traversal or another invalid path,
    // not a normal "missing file" inside the deck.
    response.writeHead(400, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end('Bad request');
    return;
  }

  fs.readFile(filePath, (error, fileContents) => {
    if (error) {
      // If the path was valid but the file is absent, return a regular 404.
      response.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      response.end('File not found');
      return;
    }

    response.writeHead(200, { 'Content-Type': getContentType(filePath) });
    response.end(fileContents);
  });
});

server.listen(0, '127.0.0.1', () => {
  const address = server.address();

  if (!address || typeof address === 'string') {
    console.error('Unable to determine the slides server address.');
    process.exit(1);
  }

  // Port `0` tells Node to ask the OS for any open port. We log the chosen
  // address so the `ddev slides` caller can open the deck in a browser.
  console.log(`Slides directory: ${slidesDirectoryName}`);
  console.log(`Deck directory: ${resolvedDeckDirectory}`);
  console.log(`Reveal.js alias: ${vendorAlias}`);
  console.log(`URL: http://127.0.0.1:${address.port}`);
  console.log('Press Ctrl+C to stop the deck.');
});
