import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, TextControl, TextareaControl, ToggleControl, SelectControl, Button, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';

const RankSavvySidebar = () => {
    // Connect to WordPress data stores
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
    const [trendGeo, setTrendGeo] = useState(''); // Empty = Worldwide
    const [isFetchingTrends, setIsFetchingTrends] = useState(false);
    const [trendError, setTrendError] = useState(null);

    const postId = useSelect((select) => select('core/editor').getCurrentPostId());

    const updateMeta = (key, value) => {
        editPost({ meta: { ...meta, [key]: value } });
    };

    const checkIndexStatus = async () => {
        setIsChecking(true);
        // MVP: Simulate API Call to GSC URL Inspection API
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
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': rankSavvyEditorConfig.nonce,
                },
                body: JSON.stringify({ post_id: postId })
            });
            const data = await response.json();
            if (response.ok) {
                setSubmitStatus({ type: 'success', message: data.message || 'Submitted successfully!' });
            } else {
                setSubmitStatus({ type: 'error', message: data.message || 'Failed to submit.' });
            }
        } catch (error) {
            setSubmitStatus({ type: 'error', message: 'An error occurred during submission.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // On-Page Analysis logic
    const wordCount = (content.match(/\b\w+\b/g) || []).length;
    const focusKeyword = meta._ranksavvy_focus_keyword || '';
    const isKeywordInTitle = focusKeyword && title.toLowerCase().includes(focusKeyword.toLowerCase());
    const isKeywordInContent = focusKeyword && content.toLowerCase().includes(focusKeyword.toLowerCase());

    const fetchTrends = async () => {
        if (!focusKeyword) return;
        setIsFetchingTrends(true);
        setTrendError(null);
        try {
            const response = await fetch(`${rankSavvyEditorConfig.apiUrl}/trends?keyword=${encodeURIComponent(focusKeyword)}&geo=${trendGeo}`, {
                headers: {
                    'X-WP-Nonce': rankSavvyEditorConfig.nonce,
                }
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

    return (
        <>
            <PluginSidebarMoreMenuItem target="ranksavvy-sidebar">
                {__('RankSavvy SEO', 'ranksavvy')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar name="ranksavvy-sidebar" title={__('RankSavvy AI SEO', 'ranksavvy')}>
                <div className="ranksavvy-editor-wrapper p-4">
                    
                    <PanelBody title={__('On-Page Analysis', 'ranksavvy')} initialOpen={true}>
                        <TextControl
                            label={__('Focus Keyword', 'ranksavvy')}
                            value={meta._ranksavvy_focus_keyword || ''}
                            onChange={(value) => updateMeta('_ranksavvy_focus_keyword', value)}
                        />
                        {focusKeyword && (
                            <ul className="mt-3 space-y-2 text-sm">
                                <li className="flex items-center">
                                    <span className={`mr-2 ${isKeywordInTitle ? 'text-green-500' : 'text-red-500'}`}>
                                        {isKeywordInTitle ? '✓' : '✗'}
                                    </span>
                                    Keyword in Title
                                </li>
                                <li className="flex items-center">
                                    <span className={`mr-2 ${isKeywordInContent ? 'text-green-500' : 'text-red-500'}`}>
                                        {isKeywordInContent ? '✓' : '✗'}
                                    </span>
                                    Keyword in Content
                                </li>
                                <li className="flex items-center">
                                    <span className={`mr-2 ${wordCount > 300 ? 'text-green-500' : 'text-orange-500'}`}>
                                        {wordCount > 300 ? '✓' : '⚠'}
                                    </span>
                                    Word Count: {wordCount} (Target: >300)
                                </li>
                            </ul>
                        )}

                        <div className="mt-4 pt-4 border-t border-slate-200">
                            <strong>Google Trends — Realtime Data</strong>
                            <p className="text-xs text-slate-500 mb-2 mt-1">Analyze search interest for your focus keyword over the last 12 months.</p>
                            
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
                                <div className="mt-2 bg-white border border-slate-200 rounded p-2" style={{ height: '180px' }}>
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={trendData}>
                                            <XAxis dataKey="time" hide={true} />
                                            <YAxis hide={true} domain={[0, 100]} />
                                            <Tooltip 
                                                labelStyle={{ color: '#64748b', fontSize: '11px' }} 
                                                itemStyle={{ color: '#0f172a', fontWeight: 'bold' }} 
                                            />
                                            <Line type="monotone" dataKey="value" stroke="#3b82f6" strokeWidth={2} dot={false} name="Interest" />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                        </div>
                    </PanelBody>

                    <PanelBody title={__('Core Meta', 'ranksavvy')} initialOpen={false}>
                        <TextControl
                            label={__('Meta Title', 'ranksavvy')}
                            help={__('Leave blank to use the post title.', 'ranksavvy')}
                            value={meta._ranksavvy_meta_title || ''}
                            onChange={(value) => updateMeta('_ranksavvy_meta_title', value)}
                        />
                        <TextareaControl
                            label={__('Meta Description', 'ranksavvy')}
                            value={meta._ranksavvy_meta_description || ''}
                            onChange={(value) => updateMeta('_ranksavvy_meta_description', value)}
                            rows={4}
                        />
                    </PanelBody>

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
                        
                        <div className="mt-4 pt-4 border-t border-slate-200">
                            <strong>Manual Indexing</strong>
                            <p className="text-xs text-slate-500 mb-2 mt-1">Force a ping to Google and Bing for this specific post.</p>
                            
                            {submitStatus && (
                                <Notice 
                                    status={submitStatus.type} 
                                    isDismissible={true} 
                                    onRemove={() => setSubmitStatus(null)}
                                    className="mb-2"
                                >
                                    {submitStatus.message}
                                </Notice>
                            )}

                            <Button isPrimary isBusy={isSubmitting} onClick={submitToIndex} className="w-full justify-center mb-4">
                                {isSubmitting ? 'Submitting...' : 'Submit to Search Engines'}
                            </Button>

                            <strong>Google Index Status</strong>
                            <div className="mt-2">
                                <Button isSecondary isBusy={isChecking} onClick={checkIndexStatus} className="w-full justify-center">
                                    Check Index Status
                                </Button>
                            </div>
                            {indexStatus && (
                                <div className="mt-2 text-sm text-slate-600 bg-slate-50 p-2 rounded">
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
    icon: 'chart-area', // Dashicon
});
