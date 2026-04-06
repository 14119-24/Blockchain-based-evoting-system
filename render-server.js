const fs = require('fs');
const http = require('http');
const path = require('path');
const { URL } = require('url');

const port = Number.parseInt(process.env.PORT || '3000', 10);
const host = '0.0.0.0';

const publicDir = path.join(__dirname, 'public');
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

function resolveStaticFile(pathname) {
    if (pathname === '/' || pathname === '') {
        return path.join(publicDir, 'index.html');
    }

    if (pathname.startsWith('/css/')) {
        return getSafePath(cssDir, pathname.slice('/css/'.length));
    }

    const directMatch = getSafePath(publicDir, pathname);
    if (!directMatch) {
        return null;
    }

    if (path.extname(directMatch) !== '') {
        return directMatch;
    }

    return directMatch + '.html';
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

    // The application backend is PHP-based, so we do not expose /api as static files.
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
