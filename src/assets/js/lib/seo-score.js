/**
 * RankSavvy SEO Score Calculator
 * 
 * Calculates a composite 0-100 SEO score from keyword, meta, and readability checks.
 * Each check contributes weighted points to the total.
 */

/**
 * Calculate the aggregate SEO score.
 * 
 * @param {object} params
 * @param {string} params.title - Post title
 * @param {string} params.content - Raw HTML content
 * @param {string} params.focusKeyword - Focus keyword
 * @param {string} params.metaTitle - Meta title override
 * @param {string} params.metaDescription - Meta description
 * @param {object} params.readability - Readability analysis results
 * @returns {{ score: number, color: string, label: string, checks: Array }}
 */
export function calculateSeoScore({ title, content, focusKeyword, metaTitle, metaDescription, readability }) {
    const checks = [];
    let earned = 0;
    let total = 0;

    const plainContent = content
        .replace(/<!--.*?-->/gs, ' ')
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const wordCount = (plainContent.match(/\b\w+\b/g) || []).length;
    const effectiveTitle = metaTitle || title;
    const lowerContent = plainContent.toLowerCase();
    const lowerTitle = title.toLowerCase();
    const lowerKeyword = focusKeyword.toLowerCase();

    // ── Keyword Checks (40 points) ──────────────────────────
    if (focusKeyword) {
        // Keyword in title (10 pts)
        const kwInTitle = lowerTitle.includes(lowerKeyword);
        checks.push({ label: 'Keyword in title', pass: kwInTitle, weight: 10 });
        if (kwInTitle) earned += 10;
        total += 10;

        // Keyword in first paragraph (8 pts)
        const firstPara = lowerContent.substring(0, 300);
        const kwInIntro = firstPara.includes(lowerKeyword);
        checks.push({ label: 'Keyword in introduction', pass: kwInIntro, weight: 8 });
        if (kwInIntro) earned += 8;
        total += 8;

        // Keyword in content (7 pts)
        const kwInContent = lowerContent.includes(lowerKeyword);
        checks.push({ label: 'Keyword in content', pass: kwInContent, weight: 7 });
        if (kwInContent) earned += 7;
        total += 7;

        // Keyword density 0.5-2.5% (8 pts)
        const kwOccurrences = (lowerContent.split(lowerKeyword).length - 1);
        const density = wordCount > 0 ? (kwOccurrences / wordCount) * 100 : 0;
        const densityOk = density >= 0.5 && density <= 2.5;
        checks.push({ label: `Keyword density: ${density.toFixed(1)}% (0.5-2.5%)`, pass: densityOk, weight: 8 });
        if (densityOk) earned += 8;
        total += 8;

        // Keyword in meta title (7 pts)
        const kwInMetaTitle = effectiveTitle.toLowerCase().includes(lowerKeyword);
        checks.push({ label: 'Keyword in meta title', pass: kwInMetaTitle, weight: 7 });
        if (kwInMetaTitle) earned += 7;
        total += 7;
    } else {
        // No keyword set — assign 0 for all keyword checks
        checks.push({ label: 'Focus keyword is set', pass: false, weight: 40 });
        total += 40;
    }

    // ── Meta Checks (25 points) ──────────────────────────
    const titleLen = effectiveTitle.length;
    const titleOk = titleLen > 0 && titleLen <= 60;
    checks.push({ label: `Meta title length: ${titleLen} chars (≤60)`, pass: titleOk, weight: 8 });
    if (titleOk) earned += 8;
    total += 8;

    const descLen = (metaDescription || '').length;
    const descOk = descLen >= 120 && descLen <= 160;
    checks.push({ label: `Meta description: ${descLen} chars (120-160)`, pass: descOk, weight: 9 });
    if (descOk) earned += 9;
    total += 9;

    const lengthOk = wordCount >= 300;
    checks.push({ label: `Content length: ${wordCount} words (≥300)`, pass: lengthOk, weight: 8 });
    if (lengthOk) earned += 8;
    total += 8;

    // ── Readability Checks (35 points) ──────────────────────────
    if (readability) {
        checks.push({ label: `Flesch score: ${readability.flesch.score} (${readability.flesch.label})`, pass: readability.flesch.pass, weight: 10 });
        if (readability.flesch.pass) earned += 10;
        total += 10;

        checks.push({ label: `Long sentences: ${readability.longSentences.value}% (≤25%)`, pass: readability.longSentences.pass, weight: 7 });
        if (readability.longSentences.pass) earned += 7;
        total += 7;

        checks.push({ label: `Passive voice: ${readability.passiveVoice.value}% (≤10%)`, pass: readability.passiveVoice.pass, weight: 6 });
        if (readability.passiveVoice.pass) earned += 6;
        total += 6;

        checks.push({ label: `Transition words: ${readability.transitionWords.value}% (≥30%)`, pass: readability.transitionWords.pass, weight: 6 });
        if (readability.transitionWords.pass) earned += 6;
        total += 6;

        checks.push({ label: 'Subheading distribution', pass: readability.subheadings.pass, weight: 6 });
        if (readability.subheadings.pass) earned += 6;
        total += 6;
    }

    const score = total > 0 ? Math.round((earned / total) * 100) : 0;

    return {
        score,
        color: score >= 80 ? '#22c55e' : score >= 50 ? '#f59e0b' : '#ef4444',
        label: score >= 80 ? 'Good' : score >= 50 ? 'Needs Work' : 'Poor',
        checks,
    };
}
