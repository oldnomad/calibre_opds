<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: 2023 Alec Kojaev <alec@kojaev.name>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Stubs;

use Exception;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use PHPUnit\Framework\MockObject\Stub;

trait StorageStub {
	protected IStorage $storage;

	protected function initStorage(string $path, bool $fake = false): void {
		$storage = $this->createStub(IStorage::class);
		$storage->method('getLocalFile')->willReturnCallback(
			fn (string $filePath): string => $fake ? $path : ($path . '/' . $filePath)
		);
		$this->storage = $storage;
	}

	private function createStorageNode(string $cls, string $type, string $name, bool $readable): Node&Stub {
		$node = $this->createStub($cls);
		$node->method('isReadable')->willReturn($readable);
		$node->method('getType')->willReturn($type);
		$node->method('getName')->willReturn($name);
		$node->method('getInternalPath')->willReturnCallback(function () use ($node) {
			$parent = $node->getParent();
			return (is_null($parent) ? '' : $parent->getInternalPath()) . '/' . $node->getName();
		});
		$node->method('getStorage')->willReturn($this->storage);
		return $node;
	}

	protected function createFileNode(string $filename, bool $readable = true): File {
		$file = $this->createStorageNode(File::class, FileInfo::TYPE_FILE, $filename, $readable);
		return $file;
	}

	protected function createFolderNode(string $dirname, array $content, bool $readable = true): Folder {
		return $this->createFolderNodeClass(Folder::class, $dirname, $content, $readable);
	}

	protected function createFolderNodeClass(string $cls, string $dirname, array $content, bool $readable = true): Folder {
		if (!is_a($cls, Folder::class, true)) {
			throw new Exception('invalid folder class ' . $cls);
		}
		$dir = $this->createStorageNode($cls, FileInfo::TYPE_FOLDER, $dirname, $readable);
		foreach ($content as $sub) {
			$sub->method('getParent')->willReturn($dir);
		}
		$dir->method('get')->willReturnCallback(function (string $path) use ($dir, $content): Node {
			if ($path === '') {
				throw new NotFoundException('cannot find file with empty name');
			}
			$parts = explode('/', $path, 2);
			foreach ($content as $sub) {
				if ($sub->getName() === $parts[0]) {
					if (isset($parts[1])) {
						if ($sub->getType() !== FileInfo::TYPE_FOLDER) {
							throw new NotFoundException('found file, expecting directory');
						}
						try {
							return $sub->get($parts[1]);
						} catch (NotFoundException $e) {
							throw new NotFoundException('not found: ' . $path, 0, $e);
						}
					}
					return $sub;
				}
			}
			throw new NotFoundException('not found: ' . $path);
		});
		return $dir;
	}
}
