<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: 2025 Alec Kojaev <alec@kojaev.name>
// SPDX-License-Identifier: AGPL-3.0-or-later

use OCA\Calibre2OPDS\AppInfo\Application;
use OCA\Calibre2OPDS\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Stubs\L10NStub;
use Stubs\LoggerInterfaceStub;
use Stubs\StorageStub;

class SettingsServiceTest extends TestCase {
	private const string UID = '123';

	use LoggerInterfaceStub;
	use StorageStub;
	use L10NStub;

	private SettingsService $service;
	private ?object $user = null;
	private ?IUserFolder $userFolder = null;
	private ?Folder $booksFolder = null;
	private array $appInfo = [
		'name' => '@name',
		'version' => '@version',
		'website' => '@website',
	];
	private array $confValues = [];

	public function setUp(): void {
		$this->initLoggerInterface();
		$this->initStorage('.', true);
		$this->initL10N('sv-SE');
		$this->booksFolder = $this->createFolderNode('Books', []);
		$this->userFolder = $this->createFolderNodeClass(IUserFolder::class, '.', [
			$this->booksFolder,
			$this->createFolderNode('BadBooks', [], false),
		]);
		$this->confValues = [];
		$config = $this->createStub(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(function ($userId, $appId, $name, $defValue) {
			$this->assertEquals(self::UID, $userId);
			$this->assertEquals(Application::APP_ID, $appId);
			return $this->confValues[$name] ?? $defValue;
		});
		$config->method('setUserValue')->willReturnCallback(function ($userId, $appId, $name, $value) {
			$this->assertEquals(self::UID, $userId);
			$this->assertEquals(Application::APP_ID, $appId);
			$this->confValues[$name] = $value;
		});
		$this->user = $this->createStub(IUser::class);
		$this->user->method('getUID')->willReturn(self::UID);
		$userService = $this->createStub(IUserSession::class);
		$userService->method('getUser')->willReturnCallback(fn () => $this->user);
		$rootFolder = $this->createStub(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturnCallback(function ($userId) {
			$this->assertEquals(self::UID, $userId);
			return $this->userFolder;
		});
		$urlGenerator = $this->createStub(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(function ($route, $params) {
			return '@route:' . $route . '?' . json_encode($params);
		});
		$urlGenerator->method('imagePath')->willReturnCallback(function ($appId, $path) {
			return '@image:' . $appId . ':' . $path;
		});
		$appManager = $this->createStub(IAppManager::class);
		$appManager->method('getAppInfo')->willReturnCallback(function ($appId) {
			$this->assertEquals(Application::APP_ID, $appId);
			return $this->appInfo;
		});
		$this->service = new SettingsService($this->logger, $config, $userService, $rootFolder, $urlGenerator, $appManager, $this->l);
	}

	public function testAppInfo(): void {
		$this->assertEquals(Application::APP_ID, $this->service->getAppId());
		$this->assertEquals('@version', $this->service->getAppVersion());
		$this->assertEquals('@name', $this->service->getAppName());
		$this->assertEquals('@website', $this->service->getAppWebsite());
	}

	public function testPaths(): void {
		$this->assertEquals('@route:calibre_opds.opds.route?[]', $this->service->getAppRouteLink('route'));
		$this->assertEquals('@route:calibre_opds.opds.route?{"param1":"value1","param2":"value2"}',
			$this->service->getAppRouteLink('route', [ 'param1' => 'value1', 'param2' => 'value2' ]));
		$this->assertEquals('@image:calibre_opds:path', $this->service->getAppImageLink('path'));
	}

	public function testLanguage(): void {
		$this->assertEquals('engelska', $this->service->getLanguageName('en'));
		// NOTE: It's impossible to test error branch, since locale_get_display_name() doesn't return false on any data.
	}

	public function testSettings(): void {
		$this->assertTrue($this->service->isLoggedIn());
		$this->assertEquals('Books', $this->service->getLibrary());
		$this->assertEquals([], $this->confValues);
		$this->assertEquals(['library' => 'Books'], $this->service->getSettings());
		$this->assertEquals($this->booksFolder, $this->service->getLibraryFolder());

		$this->assertTrue($this->service->setLibrary('Books-1'));
		$this->assertEquals('Books-1', $this->service->getLibrary());
		$this->assertEquals(['library' => 'Books-1'], $this->service->getSettings());
		$this->expectMessage = [
			'level' => 'error',
			'msg' => 'not found: Books-1',
		];
		$this->assertNull($this->service->getLibraryFolder());

		$this->assertTrue($this->service->setLibrary('BadBooks'));
		$this->expectMessage = [
			'level' => 'error',
			'msg' => 'Library root is not a readable folder',
		];
		$this->assertNull($this->service->getLibraryFolder());
	}

	public function testUnlogged(): void {
		$this->user = null;
		$this->assertFalse($this->service->isLoggedIn());
		$this->assertNull($this->service->getLibrary());
		$this->assertFalse($this->service->setLibrary('Books-1'));
		$this->assertEquals([], $this->service->getSettings());
		$this->assertNull($this->service->getLibraryFolder());
	}
}
