<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: 2023 Alec Kojaev <alec@kojaev.name>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Stubs;

use OCP\IL10N;

trait L10NStub {
	protected IL10N $l;

	protected function initL10N(?string $pref_locale = null): void {
		$l = $this->createStub(IL10N::class);
		$locale = $pref_locale ?? locale_get_default();
		$l->method('getLocaleCode')->willReturn($locale);
		$l->method('getLanguageCode')->willReturn(locale_get_display_language($locale));
		$l->method('t')->willReturnCallback(function (string $text, $parameters = []): string {
			return sprintf($text, ...$parameters);
		});
		$l->method('n')->willReturnCallback(function (string $text_singular, string $text_plural, int $count, array $parameters = []): string {
			return $this->t($text_plural, $parameters);
		});
		$l->method('l')->willReturnCallback(function (string $type, $data, array $options = []) {
			return $data;
		});
		$this->l = $l;
	}
}
