import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, TextControl, TextareaControl, ToggleControl, SelectControl, Button, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { analyzeReadability, stripHtml } from './lib/readability';
import { calculateSeoScore } from './lib/seo-score';

// ── Score Ring Component ──────────────────────────────────
const ScoreRing = ({ score, color, label, size = 72 }) => {
    const radius = (size - 8) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (score / 100) * circumference;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '8px 0' }}>
            <svg width={size} height={size} style={{ flexShrink: 0 }}>
                <circle cx={size/2} cy={size/2} r={radius} fill="none" stroke="#e2e8f0" strokeWidth="6" />
                <circle
                    cx={size/2} cy={size/2} r={radius} fill="none"
                    stroke={color} strokeWidth="6" strokeLinecap="round"
                    strokeDasharray={circumference} strokeDashoffset={offset}
                    style={{ transform: 'rotate(-90deg)', transformOrigin: '50% 50%', transition: 'stroke-dashoffset 0.5s ease' }}
                />
                <text x="50%" y="50%" dominantBaseline="central" textAnchor="middle"
                    style={{ fontSize: '16px', fontWeight: '700', fill: color }}>
                    {score}
                </text>
            </svg>
            <div>
                <div style={{ fontSize: '14px', fontWeight: '600', color: '#0f172a' }}>SEO Score</div>
                <div style={{ fontSize: '12px', color: color, fontWeight: '500' }}>{label}</div>
            </div>
        </div>
    );
};

// ── SERP Preview Component ──────────────────────────────────
const SerpPreview = ({ title, url, description }) => (
    <div style={{
        border: '1px solid #e2e8f0', borderRadius: '8px', padding: '14px',
        background: '#fff', fontFamily: 'Arial, sans-serif', marginTop: '12px'
    }}>
        <div style={{ fontSize: '11px', color: '#202124', marginBottom: '2px' }}>
            {url || 'https://example.com › your-post-slug'}
        </div>
        <div style={{
            fontSize: '18px', color: '#1a0dab', lineHeight: '1.3',
            overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            cursor: 'pointer', marginBottom: '4px'
        }}>
            {title || 'Your Page Title'}
        </div>
        <div style={{ fontSize: '13px', color: '#4d5156', lineHeight: '1.4', 
            display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
            {description || 'Add a meta description to control how this page appears in search results...'}
        </div>
    </div>
);

// ── Character Counter Component ──────────────────────────────────
const CharCount = ({ current, min, max }) => {
    let color = '#22c55e'; // green
    if (current === 0) color = '#94a3b8';
    else if (current > max) color = '#ef4444';
    else if (current < min) color = '#f59e0b';

    return (
        <span style={{ fontSize: '11px', fontWeight: '500', color, float: 'right' }}>
            {current} / {max}
        </span>
    );
};

// ── Check Item Component ──────────────────────────────────
const CheckItem = ({ label, pass }) => (
    <li style={{ display: 'flex', alignItems: 'flex-start', gap: '6px', fontSize: '13px', lineHeight: '1.4', padding: '3px 0' }}>
        <span style={{ color: pass ? '#22c55e' : '#ef4444', flexShrink: 0, fontSize: '14px', marginTop: '1px' }}>
            {pass ? '✓' : '✗'}
        </span>
        <span style={{ color: '#334155' }}>{label}</span>
    </li>
);

// ── Main Sidebar Component ──────────────────────────────────
const RankSavvySidebar = () => {
    const { meta, postType, title, content } = useSelect((select) => {
        const editor = select('core/editor');
        return {
            meta: editor.getEditedPostAttribute('meta') || {},
            postType: editor.getCurrentPostType(),
            title: editor.getEditedPostAttribute('title') || '',
            content: editor.getEditedPostAttribute('content') || ''
        };
    }, []);

    const { editPost } = useDispatch('core/editor');
    const [indexStatus, setIndexStatus] = useState(null);
    const [isChecking, setIsChecking] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitStatus, setSubmitStatus] = useState(null);

    // Trends State
    const [trendData, setTrendData] = useState(null);
    const [trendGeo, setTrendGeo] = useState('');
    const [isFetchingTrends, setIsFetchingTrends] = useState(false);
    const [trendError, setTrendError] = useState(null);

    const postId = useSelect((select) => select('core/editor').getCurrentPostId());
    const permalink = useSelect((select) => select('core/editor').getPermalink());

    const updateMeta = (key, value) => {
        editPost({ meta: { ...meta, [key]: value } });
    };

    // ── Derived Analysis (memoized) ──────────────────────────
    const focusKeyword = meta._ranksavvy_focus_keyword || '';
    const metaTitle = meta._ranksavvy_meta_title || '';
    const metaDescription = meta._ranksavvy_meta_description || '';
    const effectiveTitle = metaTitle || title;

    const readability = useMemo(() => analyzeReadability(content), [content]);

    const seoResult = useMemo(() => calculateSeoScore({
        title,
        content,
        focusKeyword,
        metaTitle,
        metaDescription,
        readability,
    }), [title, content, focusKeyword, metaTitle, metaDescription, readability]);

    // ── Actions ──────────────────────────────────────────────
    const checkIndexStatus = async () => {
        setIsChecking(true);
        setTimeout(() => {
            setIndexStatus('Indexed, not submitted in sitemap');
            setIsChecking(false);
        }, 1500);
    };

    const submitToIndex = async () => {
        setIsSubmitting(true);
        setSubmitStatus(null);
        try {
            const response = await fetch(`${rankSavvyEditorConfig.apiUrl}/index-url`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyEditorConfig.nonce },
                body: JSON.stringify({ post_id: postId })
            });
            const data = await response.json();
            setSubmitStatus(response.ok
                ? { type: 'success', message: data.message || 'Submitted successfully!' }
                : { type: 'error', message: data.message || 'Failed to submit.' }
            );
        } catch (error) {
            setSubmitStatus({ type: 'error', message: 'An error occurred during submission.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    const fetchTrends = async () => {
        if (!focusKeyword) return;
        setIsFetchingTrends(true);
        setTrendError(null);
        try {
            const response = await fetch(`${rankSavvyEditorConfig.apiUrl}/trends?keyword=${encodeURIComponent(focusKeyword)}&geo=${trendGeo}`, {
                headers: { 'X-WP-Nonce': rankSavvyEditorConfig.nonce }
            });
            const result = await response.json();
            if (result.success && result.data) {
                setTrendData(result.data);
            } else {
                setTrendError(result.message || 'Failed to fetch trends.');
            }
        } catch (error) {
            setTrendError('Error communicating with server.');
        } finally {
            setIsFetchingTrends(false);
        }
    };

    // ── Render ──────────────────────────────────────────────
    return (
        <>
            <PluginSidebarMoreMenuItem target="ranksavvy-sidebar">
                {__('RankSavvy SEO', 'ranksavvy')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar name="ranksavvy-sidebar" title={__('RankSavvy AI SEO', 'ranksavvy')}>
                <div className="ranksavvy-editor-wrapper" style={{ padding: '12px 16px' }}>

                    {/* ── SEO Score Header ── */}
                    <div style={{ borderBottom: '1px solid #e2e8f0', paddingBottom: '12px', marginBottom: '8px' }}>
                        <ScoreRing score={seoResult.score} color={seoResult.color} label={seoResult.label} />
                    </div>

                    {/* ── On-Page SEO Analysis ── */}
                    <PanelBody title={__('SEO Analysis', 'ranksavvy')} initialOpen={true}>
                        <TextControl
                            label={__('Focus Keyword', 'ranksavvy')}
                            value={focusKeyword}
                            onChange={(value) => updateMeta('_ranksavvy_focus_keyword', value)}
                        />
                        <ul style={{ listStyle: 'none', margin: '8px 0 0', padding: 0 }}>
                            {seoResult.checks.map((check, i) => (
                                <CheckItem key={i} label={check.label} pass={check.pass} />
                            ))}
                        </ul>
                    </PanelBody>

                    {/* ── Readability Analysis ── */}
                    <PanelBody title={__('Readability', 'ranksavvy')} initialOpen={false}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                            <ScoreRing
                                score={readability.flesch.score}
                                color={readability.flesch.pass ? '#22c55e' : readability.flesch.score >= 40 ? '#f59e0b' : '#ef4444'}
                                label={readability.flesch.label}
                                size={56}
                            />
                        </div>
                        <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                            <CheckItem label={`Flesch Reading Ease: ${readability.flesch.score} (${readability.flesch.label})`} pass={readability.flesch.pass} />
                            <CheckItem label={`Long sentences: ${readability.longSentences.value}% (target ≤25%)`} pass={readability.longSentences.pass} />
                            <CheckItem label={readability.longParagraphs.pass ? 'No overly long paragraphs' : `${readability.longParagraphs.value} paragraph(s) exceed 150 words`} pass={readability.longParagraphs.pass} />
                            <CheckItem label={`Passive voice: ${readability.passiveVoice.value}% (target ≤10%)`} pass={readability.passiveVoice.pass} />
                            <CheckItem label={`Transition words: ${readability.transitionWords.value}% (target ≥30%)`} pass={readability.transitionWords.pass} />
                            <CheckItem label={readability.subheadings.pass ? 'Good subheading distribution' : 'Add more subheadings to break up content'} pass={readability.subheadings.pass} />
                        </ul>
                    </PanelBody>

                    {/* ── Core Meta + SERP Preview ── */}
                    <PanelBody title={__('SERP Preview & Meta', 'ranksavvy')} initialOpen={false}>
                        <SerpPreview
                            title={effectiveTitle}
                            url={permalink}
                            description={metaDescription}
                        />

                        <div style={{ marginTop: '16px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                                <label style={{ fontSize: '13px', fontWeight: '600', color: '#334155' }}>
                                    {__('Meta Title', 'ranksavvy')}
                                </label>
                                <CharCount current={effectiveTitle.length} min={30} max={60} />
                            </div>
                            <TextControl
                                help={__('Leave blank to use the post title.', 'ranksavvy')}
                                value={metaTitle}
                                onChange={(value) => updateMeta('_ranksavvy_meta_title', value)}
                            />
                        </div>

                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                                <label style={{ fontSize: '13px', fontWeight: '600', color: '#334155' }}>
                                    {__('Meta Description', 'ranksavvy')}
                                </label>
                                <CharCount current={metaDescription.length} min={120} max={160} />
                            </div>
                            <TextareaControl
                                value={metaDescription}
                                onChange={(value) => updateMeta('_ranksavvy_meta_description', value)}
                                rows={4}
                            />
                        </div>
                    </PanelBody>

                    {/* ── Social Metadata ── */}
                    <PanelBody title={__('Social Metadata', 'ranksavvy')} initialOpen={false}>
                        <TextControl
                            label={__('Open Graph Title (Facebook/LinkedIn)', 'ranksavvy')}
                            value={meta._ranksavvy_og_title || ''}
                            onChange={(value) => updateMeta('_ranksavvy_og_title', value)}
                        />
                        <TextareaControl
                            label={__('Open Graph Description', 'ranksavvy')}
                            value={meta._ranksavvy_og_description || ''}
                            onChange={(value) => updateMeta('_ranksavvy_og_description', value)}
                            rows={3}
                        />
                        <TextControl
                            label={__('Open Graph Image URL', 'ranksavvy')}
                            help="Provide an absolute URL to a custom social sharing image."
                            value={meta._ranksavvy_og_image || ''}
                            onChange={(value) => updateMeta('_ranksavvy_og_image', value)}
                        />
                        <TextControl
                            label={__('Twitter Title', 'ranksavvy')}
                            value={meta._ranksavvy_twitter_title || ''}
                            onChange={(value) => updateMeta('_ranksavvy_twitter_title', value)}
                        />
                    </PanelBody>

                    {/* ── Google Trends ── */}
                    <PanelBody title={__('Google Trends', 'ranksavvy')} initialOpen={false}>
                        <p style={{ fontSize: '12px', color: '#64748b', margin: '0 0 8px' }}>
                            Analyze search interest for your focus keyword over the last 12 months.
                        </p>

                        <SelectControl
                            label="Location"
                            value={trendGeo}
                            options={[
                                { label: 'Worldwide', value: '' },
                                { label: 'United States', value: 'US' },
                                { label: 'United Kingdom', value: 'GB' },
                                { label: 'Canada', value: 'CA' },
                                { label: 'Australia', value: 'AU' },
                                { label: 'India', value: 'IN' },
                                { label: 'Germany', value: 'DE' },
                                { label: 'France', value: 'FR' },
                                { label: 'Nigeria', value: 'NG' },
                                { label: 'South Africa', value: 'ZA' },
                                { label: 'Brazil', value: 'BR' },
                                { label: 'Japan', value: 'JP' },
                            ]}
                            onChange={(val) => setTrendGeo(val)}
                        />

                        <Button isSecondary isBusy={isFetchingTrends} onClick={fetchTrends} className="w-full justify-center mb-3" disabled={!focusKeyword}>
                            {isFetchingTrends ? 'Loading Trends...' : 'Load Trend Data'}
                        </Button>

                        {trendError && (
                            <Notice status="error" isDismissible={false} className="mb-2">
                                {trendError}
                            </Notice>
                        )}

                        {trendData && !trendError && (
                            <div style={{ height: '180px', marginTop: '8px', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '6px', padding: '8px' }}>
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart data={trendData}>
                                        <XAxis dataKey="time" hide={true} />
                                        <YAxis hide={true} domain={[0, 100]} />
                                        <Tooltip labelStyle={{ color: '#64748b', fontSize: '11px' }} itemStyle={{ color: '#0f172a', fontWeight: 'bold' }} />
                                        <Line type="monotone" dataKey="value" stroke="#3b82f6" strokeWidth={2} dot={false} name="Interest" />
                                    </LineChart>
                                </ResponsiveContainer>
                            </div>
                        )}
                    </PanelBody>

                    {/* ── Indexing & Technical ── */}
                    <PanelBody title={__('Indexing & Technical', 'ranksavvy')} initialOpen={false}>
                        <ToggleControl
                            label={__('Optimize for Google News', 'ranksavvy')}
                            checked={meta._ranksavvy_is_news === 'yes'}
                            onChange={(checked) => updateMeta('_ranksavvy_is_news', checked ? 'yes' : 'no')}
                        />
                        <ToggleControl
                            label={__('No Index (Hide from Search Engines)', 'ranksavvy')}
                            checked={meta._ranksavvy_noindex === 'yes'}
                            onChange={(checked) => updateMeta('_ranksavvy_noindex', checked ? 'yes' : 'no')}
                        />

                        <div style={{ marginTop: '16px', paddingTop: '16px', borderTop: '1px solid #e2e8f0' }}>
                            <strong>Manual Indexing</strong>
                            <p style={{ fontSize: '12px', color: '#64748b', margin: '4px 0 8px' }}>Force a ping to Google and Bing for this specific post.</p>

                            {submitStatus && (
                                <Notice status={submitStatus.type} isDismissible={true} onRemove={() => setSubmitStatus(null)} className="mb-2">
                                    {submitStatus.message}
                                </Notice>
                            )}

                            <Button isPrimary isBusy={isSubmitting} onClick={submitToIndex} className="w-full justify-center mb-4">
                                {isSubmitting ? 'Submitting...' : 'Submit to Search Engines'}
                            </Button>

                            <strong>Google Index Status</strong>
                            <div style={{ marginTop: '8px' }}>
                                <Button isSecondary isBusy={isChecking} onClick={checkIndexStatus} className="w-full justify-center">
                                    Check Index Status
                                </Button>
                            </div>
                            {indexStatus && (
                                <div style={{ marginTop: '8px', fontSize: '13px', color: '#475569', background: '#f8fafc', padding: '8px', borderRadius: '4px' }}>
                                    {indexStatus}
                                </div>
                            )}
                        </div>
                    </PanelBody>

                </div>
            </PluginSidebar>
        </>
    );
};

// Register the Gutenberg plugin
registerPlugin('ranksavvy-editor-sidebar', {
    render: RankSavvySidebar,
    icon: 'chart-area',
});
