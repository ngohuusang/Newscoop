<?php
use Newscoop\Service\IOutputService;
use Newscoop\Service\IPublicationService;
use Newscoop\Service\IIssueService;
require_once($GLOBALS['g_campsiteDir']. "/$ADMIN_DIR/articles/article_common.php");
require_once($GLOBALS['g_campsiteDir'].'/classes/Alias.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/ShortURL.php');

header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Content-Type: text/html; charset=UTF-8");

$f_language_id = Input::Get('f_language_id', 'int', 0);
$f_language_selected = Input::Get('f_language_selected', 'int', 0);
$f_publication_id = Input::Get('f_publication_id', 'int', 0);
$f_issue_number = Input::Get('f_issue_number', 'int', 0);
$f_section_number = Input::Get('f_section_number', 'int', 0);
$f_article_number = Input::Get('f_article_number', 'int', 0);

$languageObj = new Language($f_language_selected);
$publicationObj = new Publication($f_publication_id);
$issueObj = new Issue($f_publication_id, $f_language_id, $f_issue_number);
$sectionObj = new Section($f_publication_id, $f_issue_number, $f_language_id, $f_section_number);
$articleObj = new Article($f_language_selected, $f_article_number);

$errorStr = "";

if (!$articleObj->exists()) {
	$errorStr = getGS('There was an error reading request parameters.');
	camp_html_display_error($errorStr, null, true);
}

/**
 * @author Mihai Balaceanu <mihai.balaceanu@sourcefabric.org>
 * New theme management
 */
use Newscoop\Service\Resource\ResourceId;
use Newscoop\Service\IThemeManagementService;
use Newscoop\Service\IOutputSettingIssueService;

$resourceId = new ResourceId('Publication/Edit');
$themeManagementService = $resourceId->getService(IThemeManagementService::NAME_1);
/* @var $themeManagementService \Newscoop\Service\Implementation\ThemeManagementServiceLocal */
$outputSettingIssueService = $resourceId->getService(IOutputSettingIssueService::NAME);
/* @var $outputSettingIssueService \Newscoop\Service\Implementation\OutputSettingIssueServiceDoctrine */
$issueService = $resourceId->getService(IIssueService::NAME);
/* @var $issueService \Newscoop\Service\Implementation\IssueServiceDoctrine */
$publicationService = $resourceId->getService(IPublicationService::NAME);
/* @var $publicationService \Newscoop\Service\Implementation\PublicationServiceDoctrine */
$outputIssueSettings = current($outputSettingIssueService->findByIssue($issueObj->getIssueId()));
/* @var $outputIssueSettings \Newscoop\Entity\Output\OutputSettingsIssue */

// if article page is not set secifically for issue get for publication
if ( !$outputIssueSettings || is_null(( $articlePage = $outputIssueSettings->getArticlePage())))
{
    $publicationTheme = current($themeManagementService->getThemes($f_publication_id));
    if (!$publicationTheme) {
		$errorStr = getGS('This article cannot be previewed. Please make sure it has the publication has a theme assigned.');
		camp_html_display_error($errorStr, null, true);
	}
	else
	{
    	/* @var $publicationTheme \Newscoop\Entity\Theme */
        $publicationOutputSettings = current($themeManagementService->getOutputSettings($publicationTheme));
    	/* @var $publicationOutputSettings \Newscoop\Entity\OutputSettings */
        $articlePage = $publicationOutputSettings->getArticlePage();
    	/* @var $articlePage \Newscoop\Entity\Resource */
	}
}
$templateId = $articlePage->getPath();

if (!$templateId) {
	$errorStr = getGS('This article cannot be previewed. Please make sure it has the article template selected.');
	camp_html_display_error($errorStr, null, true);
}

$templateObj = new Template($templateId);

if (!isset($_SERVER['SERVER_PORT']))
{
	$_SERVER['SERVER_PORT'] = 80;
}
$scheme = $_SERVER['SERVER_PORT'] == 443 ? 'https://' : 'http://';
$siteAlias = new Alias($publicationObj->getDefaultAliasId());
$websiteURL = $scheme.$siteAlias->getName() . $GLOBALS['Campsite']['SUBDIR'];

$accessParams = "LoginUserId=" . $g_user->getUserId()
				. "&AdminAccess=all";
if ($publicationObj->getUrlTypeId() == 1) {
	$templateObj = new Template($templateId);
	$url = "$websiteURL/tpl/" . $templateObj->getName() . "?IdLanguage=$f_language_id"
		. "&IdPublication=$f_publication_id&NrIssue=$f_issue_number&NrSection=$f_section_number"
		. "&NrArticle=$f_article_number&$accessParams";
} else {
	$url = ShortURL::GetURL($f_publication_id, $f_language_selected, null, null, $f_article_number);
	if (!is_string($url) && PEAR::isError($url)) {
		$errorStr = $url->getMessage();
	}
	$url .= '?' . $accessParams;
}

$selectedLanguage = (int)CampRequest::GetVar('f_language_selected');
$url .= "&previewLang=$selectedLanguage";
$siteTitle = (!empty($Campsite['site']['title'])) ? htmlspecialchars($Campsite['site']['title']) : putGS("Newscoop") . $Campsite['VERSION'];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en" xml:lang="en">
<head>
  <title><?php p($siteTitle); ?></title>
</head>
<?php
if ($errorStr != "") {
	camp_html_display_error($errorStr, null, true);
}

if ($g_user->hasPermission("ManageTempl") || $g_user->hasPermission("DeleteTempl")) {
	// Show dual-pane view for those with template management priviledges
?>
  <frameset rows="60%,*" border="2">
    <frame src="<?php print "$url&preview=on"; ?>" name="body" frameborder="1">
    <frame name="e" src="empty.php" frameborder="1">
  </frameset>
<?php
} else {
	// Show single pane for everyone else.
?>
  <frameset rows="100%">
    <frame src="<?php print "$url&preview=on"; ?>" name="body" frameborder="1">
  </frameset>
<?php
}
?>
</html>
