const fs = require('fs');
const http = require('http');
const path = require('path');
const { URL } = require('url');

const port = Number.parseInt(process.env.PORT || '3000', 10);
const host = '0.0.0.0';

const rootDir = __dirname;
const publicDir = path.join(__dirname, 'public');
const jsDir = path.join(publicDir, 'js');
const cssDir = path.join(__dirname, 'css');

const contentTypes = {
    '.css': 'text/css; charset=utf-8',
    '.html': 'text/html; charset=utf-8',
    '.ico': 'image/x-icon',
    '.jpeg': 'image/jpeg',
    '.jpg': 'image/jpeg',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.png': 'image/png',
    '.svg': 'image/svg+xml',
    '.txt': 'text/plain; charset=utf-8',
    '.webp': 'image/webp'
};

function fileExists(filePath) {
    try {
        return fs.statSync(filePath).isFile();
    } catch (_error) {
        return false;
    }
}

function hasAllowedStaticExtension(filePath) {
    return Object.prototype.hasOwnProperty.call(
        contentTypes,
        path.extname(filePath).toLowerCase()
    );
}

function sendJson(res, statusCode, payload) {
    res.writeHead(statusCode, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify(payload));
}

function getSafePath(baseDir, requestPath) {
    const relativePath = requestPath.replace(/^\/+/, '');
    const resolvedPath = path.resolve(baseDir, relativePath);

    if (resolvedPath !== baseDir && !resolvedPath.startsWith(baseDir + path.sep)) {
        return null;
    }

    return resolvedPath;
}

function getExistingSafePath(baseDir, requestPath) {
    const safePath = getSafePath(baseDir, requestPath);
    if (!safePath || !hasAllowedStaticExtension(safePath) || !fileExists(safePath)) {
        return null;
    }

    return safePath;
}

function getCaseInsensitivePath(baseDir, requestPath) {
    const relativePath = requestPath.replace(/^\/+/, '');
    if (!relativePath) {
        return null;
    }

    const segments = relativePath.split(/[\\/]+/).filter(Boolean);
    let currentPath = path.resolve(baseDir);

    try {
        for (let index = 0; index < segments.length; index += 1) {
            const entries = fs.readdirSync(currentPath, { withFileTypes: true });
            const match = entries.find(
                (entry) => entry.name.toLowerCase() === segments[index].toLowerCase()
            );

            if (!match) {
                return null;
            }

            currentPath = path.join(currentPath, match.name);

            const isLastSegment = index === segments.length - 1;
            if (!isLastSegment && !match.isDirectory()) {
                return null;
            }

            if (isLastSegment && (!match.isFile() || !hasAllowedStaticExtension(currentPath))) {
                return null;
            }
        }
    } catch (_error) {
        return null;
    }

    return currentPath;
}

function resolveFromBaseDir(baseDir, requestPath) {
    return getExistingSafePath(baseDir, requestPath) || getCaseInsensitivePath(baseDir, requestPath);
}

function findFirstExistingPath(candidates) {
    for (const candidate of candidates) {
        const resolvedPath = resolveFromBaseDir(candidate.baseDir, candidate.requestPath);
        if (resolvedPath) {
            return resolvedPath;
        }
    }

    return null;
}

function resolveStaticFile(pathname) {
    if (pathname === '/' || pathname === '') {
        return findFirstExistingPath([
            { baseDir: publicDir, requestPath: 'index.html' },
            { baseDir: rootDir, requestPath: 'index.html' }
        ]);
    }

    if (pathname.startsWith('/css/')) {
        const cssPath = pathname.slice('/css/'.length);
        return findFirstExistingPath([
            { baseDir: cssDir, requestPath: cssPath },
            { baseDir: rootDir, requestPath: cssPath }
        ]);
    }

    if (pathname.startsWith('/js/')) {
        const jsPath = pathname.slice('/js/'.length);
        return findFirstExistingPath([
            { baseDir: jsDir, requestPath: jsPath },
            { baseDir: rootDir, requestPath: jsPath }
        ]);
    }

    const requestPath = pathname.replace(/^\/+/, '');
    if (!requestPath) {
        return null;
    }

    if (path.extname(requestPath) !== '') {
        return findFirstExistingPath([
            { baseDir: publicDir, requestPath },
            { baseDir: rootDir, requestPath }
        ]);
    }

    return findFirstExistingPath([
        { baseDir: publicDir, requestPath: requestPath + '.html' },
        { baseDir: rootDir, requestPath: requestPath + '.html' }
    ]);
}

function sendFile(res, filePath) {
    fs.readFile(filePath, (error, data) => {
        if (error) {
            const statusCode = error.code === 'ENOENT' ? 404 : 500;
            sendJson(res, statusCode, {
                success: false,
                error: statusCode === 404 ? 'Not found' : 'Unable to load file'
            });
            return;
        }

        const ext = path.extname(filePath).toLowerCase();
        const contentType = contentTypes[ext] || 'application/octet-stream';

        res.writeHead(200, { 'Content-Type': contentType });
        res.end(data);
    });
}

const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    const pathname = decodeURIComponent(url.pathname);

    if (pathname === '/health' || pathname === '/api/health') {
        sendJson(res, 200, {
            success: true,
            message: 'Render frontend server is running'
        });
        return;
    }

    if (pathname.startsWith('/api/')) {
        sendJson(res, 501, {
            success: false,
            error: 'This Render launcher serves only the frontend. Deploy the PHP API separately.'
        });
        return;
    }

    const filePath = resolveStaticFile(pathname);
    if (!filePath) {
        sendJson(res, 400, {
            success: false,
            error: 'Invalid path'
        });
        return;
    }

    sendFile(res, filePath);
});

server.listen(port, host, () => {
    console.log(`Render server listening on http://${host}:${port}`);
});

