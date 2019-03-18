<?php

namespace Parsoid\Tests\ApiEnv;

/**
 * @covers \Parsoid\Tests\ApiEnv\SiteConfig
 */
class SiteConfigTest extends \PHPUnit\Framework\TestCase {

	private static $siteConfig;

	protected function getSiteConfig() {
		if ( self::$siteConfig === null ) {
			$helper = new TestApiHelper( $this, 'siteinfo' );
			self::$siteConfig = new SiteConfig( $helper, [] );
		}

		return self::$siteConfig;
	}

	public function testAllowedExternalImagePrefixes() {
		$this->assertSame(
			[],
			$this->getSiteConfig()->allowedExternalImagePrefixes()
		);
	}

	public function testBaseURI() {
		$this->assertSame(
			'//en.wikipedia.org/wiki/',
			$this->getSiteConfig()->baseURI()
		);
	}

	public function testRelativeLinkPrefix() {
		$this->assertSame(
			'./',
			$this->getSiteConfig()->relativeLinkPrefix()
		);
	}

	public function testBswPagePropRegexp() {
		$this->assertSame(
			// phpcs:ignore Generic.Files.LineLength.TooLong
			'/(?:^|\s)mw:PageProp/(?:(?:NOGLOBAL|DISAMBIG|NEWSECTIONLINK|NONEWSECTIONLINK|HIDDENCAT|EXPECTUNUSEDCATEGORY|INDEX|NOINDEX|STATICREDIRECT)|(?i:NOCOLLABORATIONHUBTOC|NOTOC|NOGALLERY|FORCETOC|TOC|NOEDITSECTION|NOTITLECONVERT|NOTC|NOCONTENTCONVERT|NOCC))(?=$|\s)/uS',
			$this->getSiteConfig()->bswPagePropRegexp()
		);
	}

	public function testCanonicalNamespaceId() {
		$this->assertSame( 5, $this->getSiteConfig()->canonicalNamespaceId( 'Project talk' ) );
		$this->assertSame( null, $this->getSiteConfig()->canonicalNamespaceId( 'Wikipedia talk' ) );
	}

	public function testNamespaceId() {
		$this->assertSame( 0, $this->getSiteConfig()->namespaceId( '' ) );
		$this->assertSame( 5, $this->getSiteConfig()->namespaceId( 'Wikipedia talk' ) );
		$this->assertSame( 5, $this->getSiteConfig()->namespaceId( 'WiKiPeDiA_TaLk' ) );
		$this->assertSame( null, $this->getSiteConfig()->namespaceId( 'Foobar' ) );
	}

	public function testNamespaceName() {
		$this->assertSame( '', $this->getSiteConfig()->namespaceName( 0 ) );
		$this->assertSame( 'Wikipedia talk', $this->getSiteConfig()->namespaceName( 5 ) );
		$this->assertSame( null, $this->getSiteConfig()->namespaceName( 500 ) );
	}

	public function testNamespaceHasSubpages() {
		$this->assertSame( false, $this->getSiteConfig()->namespaceHasSubpages( 0 ) );
		$this->assertSame( true, $this->getSiteConfig()->namespaceHasSubpages( 1 ) );
	}

	public function testInterwikiMagic() {
		$this->assertSame(
			true,
			$this->getSiteConfig()->interwikiMagic()
		);
	}

	public function testInterwikiMap() {
		$ret = $this->getSiteConfig()->interwikiMap();
		$this->assertInternalType( 'array', $ret );
		$this->assertSame(
			[
				'prefix' => 'zh-cn',
				'local' => true,
				'language' => true,
				'url' => 'https://zh.wikipedia.org/wiki/$1',
			],
			$ret['zh-cn']
		);
	}

	public function testIwp() {
		$this->assertSame(
			'enwiki',
			$this->getSiteConfig()->iwp()
		);
	}

	public function testLinkPrefixRegex() {
		$this->assertSame(
			null,
			$this->getSiteConfig()->linkPrefixRegex()
		);
	}

	public function testLinkTrailRegex() {
		$this->assertSame(
			'/^([a-z]+)/sD',
			$this->getSiteConfig()->linkTrailRegex()
		);
	}

	public function testLang() {
		$this->assertSame(
			'en',
			$this->getSiteConfig()->lang()
		);
	}

	public function testMainpage() {
		$this->assertSame(
			'Main Page',
			$this->getSiteConfig()->mainpage()
		);
	}

	public function testResponsiveReferences() {
		$this->assertSame(
			[ 'enabled' => true, 'threshold' => 10 ],
			$this->getSiteConfig()->responsiveReferences()
		);
	}

	public function testRtl() {
		$this->assertSame(
			false,
			$this->getSiteConfig()->rtl()
		);
	}

	public function testLangConverterEnabled() {
		$this->assertTrue( $this->getSiteConfig()->langConverterEnabled( 'zh' ) );
		$this->assertFalse( $this->getSiteConfig()->langConverterEnabled( 'de' ) );
	}

	public function testScript() {
		$this->assertSame(
			'/w/index.php',
			$this->getSiteConfig()->script()
		);
	}

	public function testScriptpath() {
		$this->assertSame(
			'/w',
			$this->getSiteConfig()->scriptpath()
		);
	}

	public function testServer() {
		$this->assertSame(
			'//en.wikipedia.org',
			$this->getSiteConfig()->server()
		);
	}

	public function testSolTransparentWikitextRegexp() {
		$redir = preg_quote( '#REDIRECT', '/' ); // PHP 7.2 doesn't escape #, while 7.3 does.
		$this->assertSame(
			// phpcs:ignore Generic.Files.LineLength.TooLong
			'!^[ \t\n\r\0\x0b]*(?:(?:(?i:' . $redir . '))[ \t\n\r\x0c]*(?::[ \t\n\r\x0c]*)?\[\[[^\]]+\]\])?(?:\[\[Category\:[^\]]*?\]\]|__(?:(?:NOGLOBAL|DISAMBIG|NEWSECTIONLINK|NONEWSECTIONLINK|HIDDENCAT|EXPECTUNUSEDCATEGORY|INDEX|NOINDEX|STATICREDIRECT)|(?i:NOCOLLABORATIONHUBTOC|NOTOC|NOGALLERY|FORCETOC|TOC|NOEDITSECTION|NOTITLECONVERT|NOTC|NOCONTENTCONVERT|NOCC))__|/<!--(?:[^-]|-(?!->))*-->/|[ \t\n\r\0\x0b])*$!i',
			$this->getSiteConfig()->solTransparentWikitextRegexp()
		);
	}

	public function testSolTransparentWikitextNoWsRegexp() {
		$redir = preg_quote( '#REDIRECT', '/' ); // PHP 7.2 doesn't escape #, while 7.3 does.
		$this->assertSame(
			// phpcs:ignore Generic.Files.LineLength.TooLong
			'!((?:(?:(?i:' . $redir . '))[ \t\n\r\x0c]*(?::[ \t\n\r\x0c]*)?\[\[[^\]]+\]\])?(?:\[\[Category\:[^\]]*?\]\]|__(?:(?:NOGLOBAL|DISAMBIG|NEWSECTIONLINK|NONEWSECTIONLINK|HIDDENCAT|EXPECTUNUSEDCATEGORY|INDEX|NOINDEX|STATICREDIRECT)|(?i:NOCOLLABORATIONHUBTOC|NOTOC|NOGALLERY|FORCETOC|TOC|NOEDITSECTION|NOTITLECONVERT|NOTC|NOCONTENTCONVERT|NOCC))__|/<!--(?:[^-]|-(?!->))*-->/)*)!i',
			$this->getSiteConfig()->solTransparentWikitextNoWsRegexp()
		);
	}

	public function testTimezoneOffset() {
		$this->assertSame(
			0,
			$this->getSiteConfig()->timezoneOffset()
		);
	}

	public function testVariants() {
		$ret = $this->getSiteConfig()->variants();
		$this->assertInternalType( 'array', $ret );
		$this->assertSame(
			[
				'base' => 'zh',
				'fallbacks' => [ 'zh-hant', 'zh-hk', 'zh-mo' ],
			],
			$ret['zh-tw'] ?? null
		);
	}

	public function testWidthOption() {
		$this->assertSame(
			220,
			$this->getSiteConfig()->widthOption()
		);
	}

	public function testMagicWords() {
		$ret = $this->getSiteConfig()->magicWords();
		$this->assertInternalType( 'array', $ret );
		$this->assertSame( 'disambiguation', $ret['__DISAMBIG__'] ?? null );
	}

	public function testMwAliases() {
		$ret = $this->getSiteConfig()->mwAliases();
		$this->assertInternalType( 'array', $ret );
		$this->assertSame(
			[
				'DEFAULTSORT:',
				'DEFAULTSORTKEY:',
				'DEFAULTCATEGORYSORT:',
			],
			$ret['defaultsort'] ?? null
		);
	}

	public function testMagicWordCanonicalName() {
		$this->assertSame(
			'img_width',
			$this->getSiteConfig()->magicWordCanonicalName( '$1px' )
		);
	}

	public function testIsMagicWord() {
		$this->assertSame( true, $this->getSiteConfig()->isMagicWord( '$1px' ) );
		$this->assertSame( false, $this->getSiteConfig()->isMagicWord( 'img_width' ) );
	}

	public function testGetMagicWordMatcher() {
		$this->assertSame(
			'/^(?:(?:SUBJECTPAGENAME|ARTICLEPAGENAME))$/',
			$this->getSiteConfig()->getMagicWordMatcher( 'subjectpagename' )
		);
		$this->assertSame(
			'/^(?!)$/',
			$this->getSiteConfig()->getMagicWordMatcher( 'doesnotexist' )
		);
	}

	public function testGetMagicPatternMatcher() {
		$matcher = $this->getSiteConfig()->getMagicPatternMatcher( [ 'img_lossy', 'img_width' ] );
		$this->assertSame( [ 'k' => 'img_width', 'v' => '123' ], $matcher( '123px' ) );
		$this->assertSame( [ 'k' => 'img_lossy', 'v' => '123' ], $matcher( 'lossy=123' ) );
		$this->assertSame( null, $matcher( 'thumb=123' ) );
	}

	public function testIsExtensionTag() {
		$this->assertTrue( $this->getSiteConfig()->isExtensionTag( 'pre' ) );
		$this->assertFalse( $this->getSiteConfig()->isExtensionTag( 'bogus' ) );
	}

	public function testGetExtensionTagNameMap() {
		$this->assertSame(
			[
				'pre' => true,
				'nowiki' => true,
				'gallery' => true,
				'indicator' => true,
				'timeline' => true,
				'hiero' => true,
				'charinsert' => true,
				'ref' => true,
				'references' => true,
				'inputbox' => true,
				'imagemap' => true,
				'source' => true,
				'syntaxhighlight' => true,
				'poem' => true,
				'categorytree' => true,
				'section' => true,
				'score' => true,
				'templatestyles' => true,
				'templatedata' => true,
				'math' => true,
				'ce' => true,
				'chem' => true,
				'graph' => true,
				'maplink' => true,
				'mapframe' => true,
			],
			$this->getSiteConfig()->getExtensionTagNameMap()
		);
	}

	public function testGetMaxTemplateDepth() {
		$this->assertSame( 40, $this->getSiteConfig()->getMaxTemplateDepth() );
	}

	public function testGetExtResourceURLPatternMatcher() {
		$matcher = $this->getSiteConfig()->getExtResourceURLPatternMatcher();
		$this->assertInternalType( 'callable', $matcher );
		$this->assertSame(
			[ 'ISBN', '12345' ],
			$matcher( 'Special:Booksources/12345' )
		);
	}

	public function testHasValidProtocol() {
		$this->assertSame(
			false,
			$this->getSiteConfig()->hasValidProtocol( 'foo bar http://www.example.com/xyz baz' )
		);
		$this->assertSame(
			true,
			$this->getSiteConfig()->hasValidProtocol( 'http://www.example.com/xyz baz' )
		);
	}

	public function testFindValidProtocol() {
		$this->assertSame(
			true,
			$this->getSiteConfig()->findValidProtocol( 'foo bar http://www.example.com/xyz baz' )
		);
	}

}
