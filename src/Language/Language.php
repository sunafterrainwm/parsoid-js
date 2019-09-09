<?php
// phpcs:ignoreFile
// phpcs:disable Generic.Files.LineLength.TooLong
/* REMOVE THIS COMMENT AFTER PORTING */
/**
 * Base class for Language objects.
 * @module
 */

namespace Parsoid;

$validityCache = new Map();
$languageNameCache = new Map();

class Language {

	public function getConverter() {
 return $this->mConverter;
 }

	/**
	 * Returns true if a language code string is of a valid form, whether or
	 * not it exists. This includes codes which are used solely for
	 * customisation via the MediaWiki namespace.
	 *
	 * @param {string} code
	 *
	 * @return bool
	 */
	public static function isValidCode( $code ) {
		if ( !$validityCache->has( $code ) ) {
			// XXX PHP version also checks against
			// MediaWikiTitleCodex::getTitleInvalidRegex()
			$validityCache->set( $code, preg_match( "/^[^:\\/\\\\\\000&<>'\"]+$/D", $code ) );
		}
		return $validityCache->get( $code );
	}

	/**
	 * Get an array of language names, indexed by code.
	 * @param {string} [inLanguage] Code of language in which to return the names.
	 *   Use null for autonyms (native names)
	 * @param {string} [include] One of:
	 *   * `all` all available languages
	 *   * `mw` only if the language is defined in MediaWiki or
	 *      `wgExtraLanguageNames` (default)
	 *   * `mwfile` only if the language is in `mw` *and* has a message file
	 * @return Map Language code => language name
	 */
	public static function fetchLanguageNames( $inLanguage, $include ) {
		$cacheKey = "{( $inLanguage === null ) ? 'null' : $inLanguage}:{$include}";
		$ret = $languageNameCache->get( $cacheKey );
		if ( !$ret ) {
			$ret = $this->fetchLanguageNamesUncached( $inLanguage, $include );
			$languageNameCache->set( $cacheKey, $ret );
		}
		return $ret;
	}

	public static function fetchLanguageNamesUncached( $inLanguage, $include ) {
		// XXX WRITE ME XXX
		return new Map();
	}
}

$module->exports->Language = $Language;
