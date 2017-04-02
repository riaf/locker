<?php
// Routes

use GuzzleHttp\Psr7;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use Ramsey\Uuid\Uuid;
use Slim\Http\UploadedFile;

$app->get('/', function ($request, $response, $args) {
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/files/{fileId}', function ($request, $response, $args) {
    $file = $this->database->fetchObject('SELECT * FROM files WHERE id = :id LIMIT 1', [
        'id' => $args['fileId'],
    ]);

    if (!$file) {
        return $response->withStatus(404);
    }

    $filename = __DIR__ . '/../var/files/' . $file->id;

    if (!file_exists($filename)) {
        return $response->withStatus(404);
    }

    return $this->renderer->render($response, 'file.phtml', [
        'file' => $file,
        'filesize' => filesize($filename),
    ]);
});

$app->get('/download/{fileId}', function ($request, $response, $args) {
    $file = $this->database->fetchObject('SELECT * FROM files WHERE id = :id LIMIT 1', [
        'id' => $args['fileId'],
    ]);

    if (!$file) {
        return $response->withStatus(404);
    }

    $filename = __DIR__ . '/../var/files/' . $file->id;

    if (!file_exists($filename)) {
        return $response->withStatus(404);
    }

    $this->database->perform('UPDATE files SET download_count = :download_count WHERE id = :id', [
        'id' => $file->id,
        'download_count' => $file->download_count + 1,
    ]);

    $stream = new Psr7\LazyOpenStream($filename, 'r');

    return $response
        ->withHeader('Content-Transfer-Encoding', 'binary')
        ->withHeader('Content-Length', filesize($filename))
        ->withHeader('Content-Disposition', 'attachment; filename*=UTF-8\'\'' . rawurlencode($file->original_filename))
        ->withBody($stream)
    ;
});

$app->get('/admin/', function ($request, $response) {
    $files = $this->database->fetchObjects('SELECT * FROM files WHERE expires_at > :now ORDER BY created_at DESC', [
        'now' => date('Y-m-d H:i:s'),
    ]);

    return $this->renderer->render($response, 'admin/index.phtml', [
        'files' => $files,
    ]);
});

$app->get('/admin/files/new', function ($request, $response) {
    return $this->renderer->render($response, 'admin/files/new.phtml');
});

$app->post('/admin/files', function ($request, $response) {
    $data = $request->getParsedBody();
    $file = $request->getUploadedFiles()['file'];

    try {
        $id = Uuid::uuid4()->toString();
    } catch (UnsatisfiedDependencyException $e) {
        $this->logger->warning($e->getMessage());

        return $response->withStatus(500);
    }

    if (!$file instanceof UploadedFile && $file->getError() != UPLOAD_ERR_OK) {
        return $response->withStatus(400);
    }

    $file->moveTo(__DIR__ . '/../var/files/' . $id);

    $this->database->perform(
        'INSERT INTO files (id, title, description, passphrase, original_filename, download_count, expires_at, created_at)'
        . ' VALUES (:id, :title, :description, :passphrase, :original_filename, :download_count, :expires_at, :created_at)',
        [
            'id' => $id,
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'passphrase' => $data['passphrase'] ?: null,
            'original_filename' => $file->getClientFilename(),
            'download_count' => 0,
            'expires_at' => (new \DateTime($data['expires_at'] ?: '+ 2 weeks'))->format('Y-m-d H:i:s'),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]
    );

    return $response
        ->withStatus(302)
        ->withHeader('Location', '/admin/')
    ;
});

$app->delete('/admin/files/{fileId}', function ($request, $response, $args) {
    $file = $this->database->fetchObject('SELECT * FROM files WHERE id = :id LIMIT 1', [
        'id' => $args['fileId'],
    ]);

    if (!$file) {
        return $response->withStatus(404);
    }

    $filename = __DIR__ . '/../var/files/' . $file->id;

    $this->database->perform('DELETE FROM files WHERE id = :id', [
        'id' => $file->id,
    ]);

    @unlink($filename);

    return $response
        ->withStatus(302)
        ->withHeader('Location', '/admin/')
    ;
});
