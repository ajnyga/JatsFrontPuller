<?php
/**
 * @file plugins/generic/jatsFrontPuller/JatsFrontPullerPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class JatsFrontPullerPlugin
 * @ingroup plugins_generic_jatsFrontPuller
 *
 * @brief JatsFrontPuller plugin class
 */
 
import('lib.pkp.classes.plugins.GenericPlugin');

class JatsFrontPullerPlugin extends GenericPlugin {

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True if plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		if (parent::register($category, $path)) {
			if ($this->getEnabled()) {

			HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'metadataFieldEdit'));

			}
			return true;
		}
		return false;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.jatsFrontPuller.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.jatsFrontPuller.description');
	}



	/**
	 * Generate and insert JATS XML Front section to metadata edit form
	 */
	function metadataFieldEdit($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];
		
		$fbv = $smarty->getFBV();
		$form = $fbv->getForm();
		
		if (get_class($form) != 'IssueEntrySubmissionReviewForm') return false;

		$submission = $form->getSubmission();
		$submissionId = $submission->getId();
		
		$request = $this->getRequest();
		$journal = $request->getContext();
		
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$issueDao = DAORegistry::getDAO('IssueDAO');
		
		$authors = $authorDao->getBySubmissionId($submissionId);
		$submission = $articleDao->getById($submissionId);
		$publishedArticle = $publishedArticleDao->getPublishedArticleByArticleId($submission->getId());
		$section = $sectionDao->getById($submission->getSectionId());
		
		$issue = $issueDao->getById($publishedArticle->getIssueId());
		
		error_log(print_r($issue, true));
		
		$printIssn = $journal->getSetting('printIssn');
		$onlineIssn = $journal->getSetting('onlineIssn');
		$publisherInstitution = $journal->getSetting('publisherInstitution');		
		
		$datePublished = $submission->getDatePublished();
		$primaryLocale = ($submission->getLanguage() != '') ? $submission->getLanguage() : $journal->getPrimaryLocale();
		
		// header
		$response = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n" . 
			"<!DOCTYPE article PUBLIC \"-//NLM//DTD JATS (Z39.96) Journal Publishing DTD v1.1d1 20130915//EN\" \"http://jats.nlm.nih.gov/publishing/1.1/JATS-journalpublishing1.dtd\">\n" .
			"<article dtd-version=\"1.1\"\n" .
			"\txmlns:mml=\"http://www.w3.org/1998/Math/MathML\"\n" .
			"\txmlns:xlink=\"http://www.w3.org/1999/xlink\"\n" .
			"\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n" .
			"\txml:lang=\"" . substr($primaryLocale, 0, 2) . "\">\n" .
			"\t<front>\n";

		// journal-meta
		$response .=
			"\t\t<journal-meta>\n" . 
			"\t\t\t<journal-id journal-id-type=\"other\">" . 
			htmlspecialchars($journal->getSetting('abbreviation', $primaryLocale) ? Core::cleanVar($journal->getSetting('abbreviation', $primaryLocale)) : Core::cleanVar($journal->getSetting('acronym', $primaryLocale))) .
			"</journal-id>\n" . 
			"\t\t\t<journal-title>" . htmlspecialchars(Core::cleanVar($journal->getLocalizedName())) . 
			"</journal-title>\n";			

		foreach ($journal->getName(null) as $locale => $title) {
			if ($locale == $primaryLocale) continue;
			$response .= "\t\t\t<trans-title xml:lang=\"" . strtoupper(substr($locale, 0, 2)) . "\">" . htmlspecialchars(Core::cleanVar($title)) . "</trans-title>\n";
		}
		
		$response .=
			(!empty($onlineIssn)?"\t\t\t<issn pub-type=\"epub\">" . htmlspecialchars(Core::cleanVar($onlineIssn)) . "</issn>":'') .
			(!empty($printIssn)?"\t\t\t<issn pub-type=\"ppub\">" . htmlspecialchars(Core::cleanVar($printIssn)) . "</issn>":'') .
			($publisherInstitution != ''?"\t\t\t<publisher><publisher-name>" . htmlspecialchars(Core::cleanVar($publisherInstitution)) . "</publisher-name></publisher>\n":'') .
			"\t\t</journal-meta>\n";

		// article-meta
		$response .=
			"\t\t<article-meta>\n" .
			"\t\t\t<article-id pub-id-type=\"other\">" . htmlspecialchars(Core::cleanVar($submission->getBestArticleId())) . "</article-id>\n" .
			(($s = $submission->getStoredPubId('doi'))?"\t\t\t<article-id pub-id-type=\"doi\">" . htmlspecialchars(Core::cleanVar($s)) . "</article-id>\n":'') .
			"\t\t\t<article-categories>\n\t\t\t\t<subj-group subj-group-type=\"heading\">\n\t\t\t\t\t<subject>" . htmlspecialchars(Core::cleanVar($section->getLocalizedTitle())) . "</subject>\n\t\t\t\t</subj-group>\n\t\t\t</article-categories>\n";
			
		$response .=	
			"\t\t\t<title-group>\n" .
			"\t\t\t\t<article-title>" . htmlspecialchars(Core::cleanVar(strip_tags($submission->getLocalizedTitle()))) . "</article-title>\n";
			
		foreach ($submission->getTitle(null) as $locale => $title) {
			if ($locale == $primaryLocale) continue;
			$response .= "\t\t\t\t<trans-title xml:lang=\"" . strtoupper(substr($locale, 0, 2)) . "\">" . htmlspecialchars(Core::cleanVar(strip_tags($title))) . "</trans-title>\n";
		}

		$response .=
			"\t\t\t</title-group>\n";
			
		// contrib-group	
		$response .=
			"\t\t\t<contrib-group>\n";

		foreach ($submission->getAuthors() as $author) {
			$response .=
				"\t\t\t\t<contrib " . ($author->getPrimaryContact()?'corresp="yes" ':'') . "contrib-type=\"author\">\n" .				
				($author->getOrcid()?"\t\t\t\t\t<contrib-id contrib-id-type=\"orcid\">" . htmlspecialchars(Core::cleanVar($author->getOrcid())) . "</contrib-id>\n":'') .
				"\t\t\t\t\t<name name-style=\"western\">\n" .
				"\t\t\t\t\t\t<surname>" . htmlspecialchars(Core::cleanVar($author->getLastName())) . "</surname>\n" .
				"\t\t\t\t\t\t<given-names>" . htmlspecialchars(Core::cleanVar($author->getFirstName()) . (($s = $author->getMiddleName()) != ''?" $s":'')) . "</given-names>\n" .
				"\t\t\t\t\t</name>\n" .
				(($s = $author->getLocalizedAffiliation()) != ''?"\t\t\t\t\t<aff>" . htmlspecialchars(Core::cleanVar($s)) . "</aff>\n":'') .
				"\t\t\t\t\t<email>" . htmlspecialchars(Core::cleanVar($author->getEmail())) . "</email>\n" .
				(($s = $author->getUrl()) != ''?"\t\t\t\t\t<uri>" . htmlspecialchars(Core::cleanVar($s)) . "</uri>\n":'') .
				"\t\t\t\t</contrib>\n";
				
		}

		$response .= "\t\t\t</contrib-group>\n";		
		
		
		// issue data
		$response .=
			($issue->getShowYear()?"\t\t\t<pub-date pub-type=\"collection\"><year>" . htmlspecialchars(Core::cleanVar($issue->getYear())) . "</year></pub-date>\n":"\t\t\t<pub-date pub-type=\"collection\"><year>EMPTY</year></pub-date>\n") .
			($issue->getShowVolume()?"\t\t\t<volume>" . htmlspecialchars(Core::cleanVar($issue->getVolume())) . "</volume>\n":"\t\t\t<volume>EMPTY</volume>\n") .
			($issue->getShowNumber()?"\t\t\t<issue>" . htmlspecialchars(Core::cleanVar($issue->getNumber())) . "</issue>\n":"\t\t\t<issue>EMPTY</issue>\n") .
			($issue->getBestIssueId()?"\t\t\t<issue-id pub-id-type=\"other\">" . htmlspecialchars(Core::cleanVar($issue->getBestIssueId())) . "</issue-id>\n":"\t\t\t<issue-id pub-id-type=\"other\">EMPTY</issue-id>\n") .			
			($issue->getLocalizedTitle()?"\t\t\t<issue-title>" . htmlspecialchars(Core::cleanVar($issue->getLocalizedTitle())) . "</issue-title>\n":"\t\t\t<issue-title>EMPTY</issue-title>\n");
			
		$matches = null;
		if (PKPString::regexp_match_get('/^[Pp][Pp]?[.]?[ ]?(\d+)$/', $submission->getPages(), $matches)) {
			$matchedPage = htmlspecialchars(Core::cleanVar($matches[1]));
			$response .= "\t\t\t\t<fpage>$matchedPage</fpage><lpage>$matchedPage</lpage>\n";
			$pageCount = 1;
		} elseif (PKPString::regexp_match_get('/^[Pp][Pp]?[.]?[ ]?(\d+)[ ]?(-|–)[ ]?([Pp][Pp]?[.]?[ ]?)?(\d+)$/', $submission->getPages(), $matches)) {
			$matchedPageFrom = htmlspecialchars(Core::cleanVar($matches[1]));
			$matchedPageTo = htmlspecialchars(Core::cleanVar($matches[4]));
			$response .=
				"\t\t\t\t<fpage>$matchedPageFrom</fpage>\n" .
				"\t\t\t\t<lpage>$matchedPageTo</lpage>\n";
			$pageCount = $matchedPageTo - $matchedPageFrom + 1;
		}	
			
		// permissions	
		$response .=
			"\t\t\t<permissions>\n" .
			"\t\t\t\t<copyright-statement>" . htmlspecialchars(__('submission.copyrightStatement', array('copyrightYear' => $submission->getCopyrightYear(), 'copyrightHolder' => $submission->getLocalizedCopyrightHolder()))) . "</copyright-statement>\n" .
			($datePublished?"\t\t\t\t<copyright-year>" . $submission->getCopyrightYear() . "</copyright-year>\n":'') .
			"\t\t\t\t<license xlink:href=\"" . $submission->getLicenseURL() . "\">\n" .
			(($s = Application::getCCLicenseBadge($submission->getLicenseURL()))?"\t\t\t\t\t<license-p>" . strip_tags($s) . "</license-p>\n":'') .
			"\t\t\t\t</license>\n" .
			"\t\t\t</permissions>\n";			

		// TODO: Handle kwd keywords/subject when decided by PKP
			
		// abstract
		$abstract = htmlspecialchars(Core::cleanVar(strip_tags($submission->getLocalizedAbstract())));
		if (!empty($abstract)) {
			$abstract = "<p>$abstract</p>";
			$response .= "\t\t\t<abstract xml:lang=\"" . strtoupper(substr($primaryLocale, 0, 2)) . "\">$abstract</abstract>\n";
		}
		if (is_array($submission->getAbstract(null))) foreach ($submission->getAbstract(null) as $locale => $abstract) {
			if ($locale == $primaryLocale || empty($abstract)) continue;
			$abstract = htmlspecialchars(Core::cleanVar(strip_tags($abstract)));
			if (empty($abstract)) continue;
			$abstract = "<p>$abstract</p>";
			$response .= "\t\t\t<abstract-trans xml:lang=\"" . strtoupper(substr($locale, 0, 2)) . "\">$abstract</abstract-trans>\n";
		}

		// end front
		$response .=
			(isset($pageCount)?"\t\t\t<counts><page-count count=\"" . (int) $pageCount. "\" /></counts>\n":'') .
			"\t\t</article-meta>\n" .
			"\t</front>\n";		
		
		
		$smarty->assign('jats', htmlspecialchars($response));
			
			
		$output .= $smarty->fetch($this->getTemplatePath() . 'output.tpl');
			
						

		return false;
	}

	
	function getTemplatePath() {
		return parent::getTemplatePath();
	}
		
	
	

}
?>
