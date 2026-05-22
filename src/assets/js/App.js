import { useState, useEffect } from '@wordpress/element';
import { Button, TextControl, TextareaControl, Notice, Spinner, ToggleControl } from '@wordpress/components';
import SetupWizard from './components/SetupWizard';

const App = () => {
    // Show wizard if URL hash is #setup or if setup_complete flag is false
    const shouldShowSetup = window.location.hash === '#setup' || (typeof rankSavvyAdminConfig !== 'undefined' && rankSavvyAdminConfig.setupComplete === '0');
    const [showSetup, setShowSetup] = useState(shouldShowSetup);
    const [activeTab, setActiveTab] = useState('dashboard');
    const [gscData, setGscData] = useState(null);

    // Settings State
    const [settings, setSettings] = useState({
        google_indexing_key: '',
        indexnow_key: '',
        auto_index: true,
        google_verify: '',
        bing_verify: '',
        yandex_verify: '',
        pinterest_verify: '',
        breadcrumb_separator: '›',
        breadcrumb_home_label: 'Home',
        breadcrumb_auto_insert: 'none',
        ai_provider: 'openai',
        openai_key: '',
        anthropic_key: '',
        ollama_url: 'http://localhost:11434',
        default_share_image: '',
    });

    const [isTestingAi, setIsTestingAi] = useState(false);
    const [aiTestResult, setAiTestResult] = useState(null);

    const testAiConnection = async () => {
        setIsTestingAi(true);
        setAiTestResult(null);
        try {
            const response = await fetch(`${rankSavvyAdminConfig.apiUrl}/settings/ai/test-connection`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': rankSavvyAdminConfig.nonce,
                },
                body: JSON.stringify({
                    ai_provider: settings.ai_provider,
                    openai_key: settings.openai_key,
                    anthropic_key: settings.anthropic_key,
                    ollama_url: settings.ollama_url
                })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                setAiTestResult({ type: 'success', message: data.message || 'Connection successful!' });
            } else {
                setAiTestResult({ type: 'error', message: data.message || 'Connection failed.' });
            }
        } catch (error) {
            setAiTestResult({ type: 'error', message: 'An error occurred during testing.' });
        } finally {
            setIsTestingAi(false);
        }
    };

    const openMediaLibrary = () => {
        if (!window.wp || !window.wp.media) return;
        const frame = window.wp.media({
            title: 'Select Default Share Image',
            multiple: false,
            library: { type: 'image' },
            button: { text: 'Use Image' }
        });
        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            setSettings(prev => ({ ...prev, default_share_image: attachment.url }));
        });
        frame.open();
    };
    const [isSaving, setIsSaving] = useState(false);
    const [saveStatus, setSaveStatus] = useState(null);
    const [isLoadingSettings, setIsLoadingSettings] = useState(true);

    useEffect(() => {
        // MVP: Simulate loading Google Search Console data
        if (activeTab === 'dashboard' && !gscData) {
            setTimeout(() => {
                setGscData({
                    totalClicks: '24,592',
                    totalImpressions: '1.2M',
                    avgCtr: '2.4%',
                    avgPosition: '14.2',
                    topKeywords: [
                        { keyword: 'seo plugin for wordpress', clicks: 1200, pos: 3 },
                        { keyword: 'ai search optimization', clicks: 840, pos: 2 },
                        { keyword: 'rank higher on google', clicks: 650, pos: 5 },
                    ]
                });
            }, 1000);
        }

        if (activeTab === 'settings' && isLoadingSettings) {
            fetchSettings();
        }
    }, [activeTab]);

    const fetchSettings = async () => {
        try {
            const response = await fetch(`${rankSavvyAdminConfig.apiUrl}/settings`, {
                headers: {
                    'X-WP-Nonce': rankSavvyAdminConfig.nonce,
                }
            });
            if (response.ok) {
                const data = await response.json();
                setSettings({
                    google_indexing_key: data.google_indexing_key || '',
                    indexnow_key: data.indexnow_key || '',
                    auto_index: data.auto_index !== undefined ? data.auto_index : true,
                    google_verify: data.google_verify || '',
                    bing_verify: data.bing_verify || '',
                    yandex_verify: data.yandex_verify || '',
                    pinterest_verify: data.pinterest_verify || '',
                    breadcrumb_separator: data.breadcrumb_separator || '›',
                    breadcrumb_home_label: data.breadcrumb_home_label || 'Home',
                    breadcrumb_auto_insert: data.breadcrumb_auto_insert || 'none',
                    ai_provider: data.ai_provider || 'openai',
                    openai_key: data.openai_key || '',
                    anthropic_key: data.anthropic_key || '',
                    ollama_url: data.ollama_url || 'http://localhost:11434',
                    default_share_image: data.default_share_image || '',
                });
            }
        } catch (error) {
            console.error("Failed to fetch settings", error);
        } finally {
            setIsLoadingSettings(false);
        }
    };

    const saveSettings = async () => {
        setIsSaving(true);
        setSaveStatus(null);
        try {
            const response = await fetch(`${rankSavvyAdminConfig.apiUrl}/settings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': rankSavvyAdminConfig.nonce,
                },
                body: JSON.stringify(settings)
            });
            
            if (response.ok) {
                setSaveStatus({ type: 'success', message: 'Settings saved successfully!' });
            } else {
                setSaveStatus({ type: 'error', message: 'Failed to save settings.' });
            }
        } catch (error) {
            setSaveStatus({ type: 'error', message: 'An error occurred while saving.' });
        } finally {
            setIsSaving(false);
        }
    };

    // Robots.txt State
    const [robotsTxt, setRobotsTxt] = useState('');
    const [blockAiBots, setBlockAiBots] = useState(false);
    const [isLoadingRobots, setIsLoadingRobots] = useState(false);
    const [robotsLoaded, setRobotsLoaded] = useState(false);
    const [isSavingRobots, setIsSavingRobots] = useState(false);
    const [robotsStatus, setRobotsStatus] = useState(null);

    useEffect(() => {
        if (activeTab === 'technical' && !robotsLoaded) {
            setIsLoadingRobots(true);
            fetch(`${rankSavvyAdminConfig.apiUrl}/robots-txt`, {
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            })
            .then(r => r.json())
            .then(data => {
                setRobotsTxt(data.content || '');
                setBlockAiBots(!!data.block_ai_bots);
                setRobotsLoaded(true);
            })
            .catch(() => {})
            .finally(() => setIsLoadingRobots(false));
        }
    }, [activeTab]);

    const saveRobotsTxt = async () => {
        setIsSavingRobots(true);
        setRobotsStatus(null);
        try {
            const response = await fetch(`${rankSavvyAdminConfig.apiUrl}/robots-txt`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify({ content: robotsTxt, block_ai_bots: blockAiBots })
            });
            setRobotsStatus(response.ok
                ? { type: 'success', message: 'robots.txt and AI Crawler rules saved successfully!' }
                : { type: 'error', message: 'Failed to save settings.' }
            );
        } catch (error) {
            setRobotsStatus({ type: 'error', message: 'An error occurred.' });
        } finally {
            setIsSavingRobots(false);
        }
    };

    // Technical SEO Sub-Tabs
    const [techSubTab, setTechSubTab] = useState('robots');

    // Sitemaps State
    const [sitemapsData, setSitemapsData] = useState({
        enable_index_sitemap: true,
        enable_news_sitemap: false,
        enable_video_sitemap: false,
        exclude_types: [],
        exclude_posts: '',
        sitemap_changefreq_post: 'weekly',
        sitemap_changefreq_page: 'weekly',
        sitemap_priority_post: '0.6',
        sitemap_priority_page: '0.8',
        available_types: []
    });
    const [isLoadingSitemaps, setIsLoadingSitemaps] = useState(false);
    const [isSavingSitemaps, setIsSavingSitemaps] = useState(false);
    const [sitemapsStatus, setSitemapsStatus] = useState(null);

    // Global Schema Rules State
    const [schemaRules, setSchemaRules] = useState([]);
    const [availablePostTypes, setAvailablePostTypes] = useState([]);
    const [availableCategories, setAvailableCategories] = useState([]);
    const [isLoadingSchemaRules, setIsLoadingSchemaRules] = useState(false);
    const [isSavingSchemaRules, setIsSavingSchemaRules] = useState(false);
    const [schemaRulesStatus, setSchemaRulesStatus] = useState(null);

    const fetchSchemaRules = async () => {
        setIsLoadingSchemaRules(true);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/schema/global-rules`, {
                headers: {
                    'X-WP-Nonce': rankSavvyAdminConfig.nonce
                }
            });
            const data = await r.json();
            if (data) {
                setSchemaRules(data.rules || []);
                setAvailablePostTypes(data.post_types || []);
                setAvailableCategories(data.categories || []);
            }
        } catch (error) {
            console.error('Error fetching global rules:', error);
        }
        setIsLoadingSchemaRules(false);
    };

    const saveSchemaRules = async (newRules) => {
        setIsSavingSchemaRules(true);
        setSchemaRulesStatus(null);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/schema/global-rules`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': rankSavvyAdminConfig.nonce
                },
                body: JSON.stringify({ rules: newRules })
            });
            const data = await r.json();
            if (r.ok && data.success) {
                setSchemaRules(data.rules);
                setSchemaRulesStatus({ type: 'success', message: 'Global schema rules saved successfully!' });
            } else {
                setSchemaRulesStatus({ type: 'error', message: 'Failed to save global schema rules.' });
            }
        } catch (error) {
            setSchemaRulesStatus({ type: 'error', message: 'An error occurred while saving.' });
        }
        setIsSavingSchemaRules(false);
    };

    // Social Accounts State
    const [socialAccounts, setSocialAccounts] = useState([]);
    const [globalAutoShare, setGlobalAutoShare] = useState(true);
    const [socialCategories, setSocialCategories] = useState([]);
    const [isLoadingSocial, setIsLoadingSocial] = useState(false);
    const [socialApps, setSocialApps] = useState({});
    const [isSavingSocialApps, setIsSavingSocialApps] = useState(false);
    const [editingAccount, setEditingAccount] = useState(null);
    const [isSavingAccount, setIsSavingAccount] = useState(false);
    
    const fetchSocialAccounts = async () => {
        setIsLoadingSocial(true);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/social/accounts`, {
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            });
            const data = await r.json();
            if (data) {
                setSocialAccounts(data.accounts || []);
                setGlobalAutoShare(data.global_auto_share !== undefined ? data.global_auto_share : true);
                setSocialCategories(data.categories || []);
            }
            
            const rSettings = await fetch(`${rankSavvyAdminConfig.apiUrl}/social/settings`, {
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            });
            const settingsData = await rSettings.json();
            if (settingsData && settingsData.apps) {
                setSocialApps(settingsData.apps);
            }
        } catch (e) {}
        setIsLoadingSocial(false);
    };

    const updateSocialAccount = async (id, payload) => {
        setIsSavingAccount(true);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/social/accounts/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify(payload)
            });
            const data = await r.json();
            if (data.success) {
                setSocialAccounts(data.accounts || []);
                setEditingAccount(null);
            }
        } catch (e) {}
        setIsSavingAccount(false);
    };

    const deleteSocialAccount = async (id) => {
        if (!confirm('Are you sure you want to disconnect this account?')) return;
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/social/accounts/${id}`, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            });
            const data = await r.json();
            if (data.success) {
                setSocialAccounts(data.accounts);
            }
        } catch (e) {}
    };

    const toggleGlobalAutoShare = async (val) => {
        setGlobalAutoShare(val);
        try {
            await fetch(`${rankSavvyAdminConfig.apiUrl}/social/accounts`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify({ global_auto_share: val })
            });
        } catch (e) {}
    };

    const handleOAuthConnect = (network) => {
        if (!socialApps[network] || !socialApps[network].app_id) {
            alert(`Please configure your ${network} App ID and Secret in the Platform API Settings first.`);
            return;
        }

        const clientId = socialApps[network].app_id;
        const redirectUri = encodeURIComponent(`${rankSavvyAdminConfig.apiUrl}/social/oauth-callback?network=${network}`);

        let authUrl = '';
        if (network === 'facebook') {
            authUrl = `https://www.facebook.com/v19.0/dialog/oauth?client_id=${clientId}&redirect_uri=${redirectUri}&scope=pages_manage_posts,pages_read_engagement`;
        } else if (network === 'twitter') {
            // Simplistic OAuth2 URL for X
            authUrl = `https://twitter.com/i/oauth2/authorize?response_type=code&client_id=${clientId}&redirect_uri=${redirectUri}&scope=tweet.read%20tweet.write%20users.read%20offline.access&state=state&code_challenge=challenge&code_challenge_method=plain`;
        } else if (network === 'linkedin') {
            authUrl = `https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=${clientId}&redirect_uri=${redirectUri}&state=state&scope=w_member_social`;
        } else if (network === 'pinterest') {
            authUrl = `https://www.pinterest.com/oauth/?client_id=${clientId}&redirect_uri=${redirectUri}&response_type=code&scope=boards:read,pins:read,pins:write`;
        }

        window.location.href = authUrl;
    };

    const saveSocialSettings = async () => {
        setIsSavingSocialApps(true);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/social/settings`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify(socialApps)
            });
            const data = await r.json();
            if (data.success && data.apps) {
                setSocialApps(data.apps);
            }
        } catch (e) {}
        setIsSavingSocialApps(false);
    };

    // Redirects State
    const [redirects, setRedirects] = useState([]);
    const [isLoadingRedirects, setIsLoadingRedirects] = useState(false);
    const [newRedirect, setNewRedirect] = useState({ source: '', target: '', code: 301, is_regex: false });
    const [redirectsStatus, setRedirectsStatus] = useState(null);

    // 404 logs State
    const [logs404, setLogs404] = useState([]);
    const [isLoading404, setIsLoading404] = useState(false);
    const [logs404Status, setLogs404Status] = useState(null);

    // Fetch Sitemaps Config
    const fetchSitemapsSettings = async () => {
        setIsLoadingSitemaps(true);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/settings/sitemaps`, {
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            });
            if (r.ok) {
                const data = await r.json();
                setSitemapsData(data);
            }
        } catch (e) {}
        setIsLoadingSitemaps(false);
    };

    // Save Sitemaps Config
    const saveSitemapsSettings = async () => {
        setIsSavingSitemaps(true);
        setSitemapsStatus(null);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/settings/sitemaps`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify(sitemapsData)
            });
            if (r.ok) {
                setSitemapsStatus({ type: 'success', message: 'Sitemap settings saved successfully!' });
            } else {
                setSitemapsStatus({ type: 'error', message: 'Failed to save sitemap settings.' });
            }
        } catch (e) {
            setSitemapsStatus({ type: 'error', message: 'An error occurred.' });
        }
        setIsSavingSitemaps(false);
    };

    // Fetch Redirects
    const fetchRedirects = async () => {
        setIsLoadingRedirects(true);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/redirects`, {
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            });
            if (r.ok) {
                const data = await r.json();
                setRedirects(data);
            }
        } catch (e) {}
        setIsLoadingRedirects(false);
    };

    // Add Redirect
    const addRedirect = async () => {
        setRedirectsStatus(null);
        if (!newRedirect.source || !newRedirect.target) {
            setRedirectsStatus({ type: 'error', message: 'Source and Target paths are required.' });
            return;
        }
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/redirects`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify(newRedirect)
            });
            const res = await r.json();
            if (r.ok) {
                setRedirects(res.redirects || []);
                setNewRedirect({ source: '', target: '', code: 301, is_regex: false });
                setRedirectsStatus({ type: 'success', message: 'Redirect rule added successfully!' });
            } else {
                setRedirectsStatus({ type: 'error', message: res.message || 'Failed to add redirect.' });
            }
        } catch (e) {
            setRedirectsStatus({ type: 'error', message: 'An error occurred.' });
        }
    };

    // Delete Redirect
    const deleteRedirect = async (id) => {
        setRedirectsStatus(null);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/redirects/delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify({ id })
            });
            const res = await r.json();
            if (r.ok) {
                setRedirects(res.redirects || []);
                setRedirectsStatus({ type: 'success', message: 'Redirect rule deleted.' });
            }
        } catch (e) {}
    };

    // Fetch 404 Logs
    const fetch404Logs = async () => {
        setIsLoading404(true);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/errors/404`, {
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            });
            if (r.ok) {
                const data = await r.json();
                setLogs404(data);
            }
        } catch (e) {}
        setIsLoading404(false);
    };

    // Clear specific 404 Log
    const delete404Log = async (id) => {
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/errors/404`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify({ id })
            });
            const res = await r.json();
            if (r.ok) {
                setLogs404(res.logs || []);
            }
        } catch (e) {}
    };

    // Clear all 404 Logs
    const clearAll404Logs = async () => {
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/errors/404`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify({})
            });
            const res = await r.json();
            if (r.ok) {
                setLogs404(res.logs || []);
            }
        } catch (e) {}
    };

    // Quick add 404 URL to Redirect creation
    const convert404ToRedirect = (uri) => {
        setNewRedirect({ source: uri, target: '', code: 301, is_regex: false });
        setTechSubTab('redirects');
    };

    // LLM Agent Config State
    const [llmsData, setLlmsData] = useState({
        description: '',
        allow_index: true,
        allow_training: false,
        contact_url: '',
        extra_notes: '',
        preview: ''
    });
    const [isLoadingLlms, setIsLoadingLlms] = useState(false);
    const [isSavingLlms, setIsSavingLlms] = useState(false);
    const [llmsStatus, setLlmsStatus] = useState(null);

    const fetchLlmsSettings = async () => {
        setIsLoadingLlms(true);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/llms-txt/config`, {
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            });
            if (r.ok) {
                const data = await r.json();
                setLlmsData(data);
            }
        } catch (e) {}
        setIsLoadingLlms(false);
    };

    const saveLlmsSettings = async () => {
        setIsSavingLlms(true);
        setLlmsStatus(null);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/llms-txt/config`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify(llmsData)
            });
            if (r.ok) {
                const data = await r.json();
                setLlmsData(data.config);
                setLlmsStatus({ type: 'success', message: 'llms.txt configuration saved!' });
            } else {
                setLlmsStatus({ type: 'error', message: 'Failed to save llms.txt configuration.' });
            }
        } catch (e) {
            setLlmsStatus({ type: 'error', message: 'An error occurred.' });
        }
        setIsSavingLlms(false);
    };

    // Bulk Meta State
    const [bulkMeta, setBulkMeta] = useState({ items: [], total: 0, pages: 1, page: 1, post_types: [] });
    const [bulkMetaParams, setBulkMetaParams] = useState({ page: 1, post_type: 'post', search: '' });
    const [isLoadingBulkMeta, setIsLoadingBulkMeta] = useState(false);
    const [bulkMetaEdits, setBulkMetaEdits] = useState({});
    const [isSavingBulkMeta, setIsSavingBulkMeta] = useState(false);
    const [bulkMetaStatus, setBulkMetaStatus] = useState(null);

    const fetchBulkMeta = async () => {
        setIsLoadingBulkMeta(true);
        try {
            const params = new URLSearchParams(bulkMetaParams).toString();
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/bulk-meta?${params}`, {
                headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
            });
            if (r.ok) {
                const data = await r.json();
                setBulkMeta(data);
                setBulkMetaEdits({}); // clear unsaved edits on fetch
            }
        } catch (e) {}
        setIsLoadingBulkMeta(false);
    };

    const handleBulkMetaEdit = (id, field, value) => {
        setBulkMetaEdits(prev => ({
            ...prev,
            [id]: { ...prev[id], id, [field]: value }
        }));
    };

    const saveBulkMetaEdits = async () => {
        const updates = Object.values(bulkMetaEdits);
        if (updates.length === 0) return;
        setIsSavingBulkMeta(true);
        setBulkMetaStatus(null);
        try {
            const r = await fetch(`${rankSavvyAdminConfig.apiUrl}/bulk-meta`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify({ updates })
            });
            if (r.ok) {
                setBulkMetaStatus({ type: 'success', message: 'Meta updates saved!' });
                fetchBulkMeta();
            } else {
                setBulkMetaStatus({ type: 'error', message: 'Failed to save meta.' });
            }
        } catch (e) {
            setBulkMetaStatus({ type: 'error', message: 'An error occurred.' });
        }
        setIsSavingBulkMeta(false);
    };

    // Auto-fetch data on active tab transitions
    useEffect(() => {
        if (activeTab === 'sitemaps') {
            fetchSitemapsSettings();
        }
        if (activeTab === 'schema') {
            fetchSchemaRules();
        }
        if (activeTab === 'social') {
            fetchSocialAccounts();
        }
        if (activeTab === 'llms') {
            fetchLlmsSettings();
        }
        if (activeTab === 'bulk_meta') {
            fetchBulkMeta();
        }
        if (activeTab === 'technical') {
            if (techSubTab === 'redirects') {
                fetchRedirects();
            } else if (techSubTab === '404s') {
                fetch404Logs();
            }
        }
    }, [activeTab, techSubTab, bulkMetaParams.page, bulkMetaParams.post_type]);

    return (
        <>
        {showSetup && (
            <div className="ranksavvy-wrapper mt-5 p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                <SetupWizard onComplete={() => { setShowSetup(false); window.location.hash = ''; }} />
            </div>
        )}
        {!showSetup && (
        <div className="ranksavvy-wrapper mt-5 p-6 bg-white rounded-lg shadow-sm border border-gray-200">
            <header className="mb-8 border-b pb-4 flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 m-0">RankSavvy</h1>
                    <p className="text-slate-500 m-0 mt-1">AI-First Search Engine Optimization</p>
                </div>
                <div className="flex gap-2">
                    <button className="px-4 py-2 bg-brand-500 text-white rounded font-medium hover:bg-brand-600 transition-colors">
                        Run Audit
                    </button>
                </div>
            </header>

            <div className="flex gap-6">
                <aside className="w-64 flex-shrink-0">
                    <nav className="flex flex-col gap-1">
                        <NavItem label="Dashboard & GSC" active={activeTab === 'dashboard'} onClick={() => setActiveTab('dashboard')} />
                        <NavItem label="Settings & Integrations" active={activeTab === 'settings'} onClick={() => setActiveTab('settings')} />
                        <NavItem label="Technical SEO" active={activeTab === 'technical'} onClick={() => setActiveTab('technical')} />
                        <NavItem label="XML Sitemaps" active={activeTab === 'sitemaps'} onClick={() => setActiveTab('sitemaps')} />
                        <NavItem label="Schema Markup" active={activeTab === 'schema'} onClick={() => setActiveTab('schema')} />
                        <NavItem label="Social & Sharing" active={activeTab === 'social'} onClick={() => setActiveTab('social')} />
                        <NavItem label="LLM Agent Config" active={activeTab === 'llms'} onClick={() => setActiveTab('llms')} />
                        <NavItem label="Bulk Meta Editor" active={activeTab === 'bulk_meta'} onClick={() => setActiveTab('bulk_meta')} />
                    </nav>
                </aside>

                <main className="flex-1">
                    {activeTab === 'dashboard' && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-semibold m-0 text-slate-800">Google Search Console Overview</h2>
                            {!gscData ? (
                                <div className="p-8 text-center text-slate-500">Loading Search Console Data...</div>
                            ) : (
                                <>
                                    <div className="grid grid-cols-4 gap-4">
                                        <StatCard title="Total Clicks" value={gscData.totalClicks} type="success" />
                                        <StatCard title="Impressions" value={gscData.totalImpressions} />
                                        <StatCard title="Avg. CTR" value={gscData.avgCtr} />
                                        <StatCard title="Avg. Position" value={gscData.avgPosition} type="success" />
                                    </div>
                                    <div className="mt-8 border rounded-lg overflow-hidden">
                                        <table className="w-full text-left border-collapse">
                                            <thead>
                                                <tr className="bg-slate-50 border-b">
                                                    <th className="p-3 text-sm font-semibold text-slate-600">Top Keywords</th>
                                                    <th className="p-3 text-sm font-semibold text-slate-600">Clicks</th>
                                                    <th className="p-3 text-sm font-semibold text-slate-600">Position</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {gscData.topKeywords.map((kw, i) => (
                                                    <tr key={i} className="border-b last:border-0 hover:bg-slate-50">
                                                        <td className="p-3 text-sm font-medium text-slate-800">{kw.keyword}</td>
                                                        <td className="p-3 text-sm text-slate-600">{kw.clicks}</td>
                                                        <td className="p-3 text-sm text-slate-600">{kw.pos}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </>
                            )}
                        </div>
                    )}

                    {activeTab === 'settings' && (
                        <div className="space-y-6 max-w-3xl">
                            <h2 className="text-xl font-semibold m-0 text-slate-800">Global Settings & Integrations</h2>
                            <p className="text-slate-600">Configure search engine APIs, webmaster verification codes, and site-wide breadcrumb preferences below.</p>
                            {isLoadingSettings ? (
                                <div className="p-8 text-center"><Spinner /></div>
                            ) : (
                                <div className="bg-slate-50 p-6 rounded-lg border border-slate-200 space-y-6">
                                    {saveStatus && (
                                        <Notice status={saveStatus.type} isDismissible={true} onRemove={() => setSaveStatus(null)}>
                                            {saveStatus.message}
                                        </Notice>
                                    )}

                                    {/* ── SECTION 1: Search Engine APIs ── */}
                                    <div>
                                        <h3 className="text-lg font-semibold text-slate-800 mb-2">Search Engine Instant Indexing</h3>
                                        <p className="text-sm text-slate-500 mb-4">Pings search engines instantly upon post creation or update.</p>
                                        
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-xs font-semibold text-slate-700 mb-1">Google Indexing Service Account JSON</label>
                                                <TextareaControl value={settings.google_indexing_key} onChange={(val) => setSettings({ ...settings, google_indexing_key: val })} rows={6} placeholder='{"type": "service_account", ...}' />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-semibold text-slate-700 mb-1">Bing IndexNow API Key</label>
                                                <TextControl value={settings.indexnow_key} onChange={(val) => setSettings({ ...settings, indexnow_key: val })} placeholder="e.g. 1a2b3c4d5e6f7g8h9i0j" />
                                            </div>
                                            <ToggleControl label="Enable Automatic Indexing" help="Automatically submits URLs on post updates/publishing." checked={settings.auto_index} onChange={(val) => setSettings({ ...settings, auto_index: val })} />
                                        </div>
                                    </div>

                                    {/* ── SECTION 2: Webmaster Tools Verification ── */}
                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-semibold text-slate-800 mb-2">Webmaster Tools Verification</h3>
                                        <p className="text-sm text-slate-500 mb-4">Paste the verification code (usually found in the content="..." attribute of the meta tag) to verify your domain.</p>
                                        
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <TextControl
                                                label="Google Search Console verification code"
                                                value={settings.google_verify}
                                                onChange={(val) => setSettings({ ...settings, google_verify: val })}
                                                placeholder="e.g. AbC123xyz..."
                                            />
                                            <TextControl
                                                label="Bing Webmaster tools verification code"
                                                value={settings.bing_verify}
                                                onChange={(val) => setSettings({ ...settings, bing_verify: val })}
                                                placeholder="e.g. 1234567890ABCDEF..."
                                            />
                                            <TextControl
                                                label="Yandex Webmaster verification code"
                                                value={settings.yandex_verify}
                                                onChange={(val) => setSettings({ ...settings, yandex_verify: val })}
                                                placeholder="e.g. a1b2c3d4e5f6..."
                                            />
                                            <TextControl
                                                label="Pinterest verification code"
                                                value={settings.pinterest_verify}
                                                onChange={(val) => setSettings({ ...settings, pinterest_verify: val })}
                                                placeholder="e.g. 0987654321fedcba..."
                                            />
                                        </div>
                                    </div>

                                    {/* ── SECTION 3: Breadcrumbs Navigation ── */}
                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-semibold text-slate-800 mb-2">Breadcrumb Navigation</h3>
                                        <p className="text-sm text-slate-500 mb-4">Set up schema-compliant breadcrumbs with automated insertion rules.</p>
                                        
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <TextControl
                                                label="Home Label"
                                                value={settings.breadcrumb_home_label}
                                                onChange={(val) => setSettings({ ...settings, breadcrumb_home_label: val })}
                                            />
                                            <TextControl
                                                label="Breadcrumb Separator Symbol"
                                                value={settings.breadcrumb_separator}
                                                onChange={(val) => setSettings({ ...settings, breadcrumb_separator: val })}
                                            />
                                        </div>

                                        <div style={{ marginBottom: '16px' }}>
                                            <label className="block text-xs font-semibold text-slate-700 mb-2">Theme Insertion Mode</label>
                                            <select
                                                value={settings.breadcrumb_auto_insert}
                                                onChange={(e) => setSettings({ ...settings, breadcrumb_auto_insert: e.target.value })}
                                                style={{ width: '100%', height: '36px', padding: '0 8px', border: '1px solid #cbd5e1', borderRadius: '4px', background: '#fff' }}
                                            >
                                                <option value="none">Manual insertion (PHP callback / shortcode only)</option>
                                                <option value="before_content">Automatically prepend before post content block</option>
                                            </select>
                                        </div>
                                        <p className="text-xs text-slate-400">
                                            You can manually place breadcrumbs anywhere in your template files with: <code>&lt;?php ranksavvy_breadcrumbs(); ?&gt;</code> or shortcode: <code>[ranksavvy_breadcrumbs]</code>.
                                        </p>
                                    </div>

                                    {/* ── SECTION 4: AI Integration Vault ── */}
                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-semibold text-slate-800 mb-2">AI Integration Vault</h3>
                                        <p className="text-sm text-slate-500 mb-4">Select your active AI provider and supply securely encrypted credentials to unlock real-time AEO suggestions.</p>
                                        
                                        {aiTestResult && (
                                            <Notice status={aiTestResult.type} isDismissible={true} onRemove={() => setAiTestResult(null)} className="mb-4">
                                                {aiTestResult.message}
                                            </Notice>
                                        )}

                                        <div style={{ marginBottom: '16px' }}>
                                            <label className="block text-xs font-semibold text-slate-700 mb-2">Active AI Provider</label>
                                            <select
                                                value={settings.ai_provider}
                                                onChange={(e) => setSettings({ ...settings, ai_provider: e.target.value })}
                                                style={{ width: '100%', height: '36px', padding: '0 8px', border: '1px solid #cbd5e1', borderRadius: '4px', background: '#fff' }}
                                            >
                                                <option value="openai">OpenAI (GPT-4o-mini)</option>
                                                <option value="anthropic">Anthropic (Claude 3.5 Sonnet)</option>
                                                <option value="ollama">Ollama (Local Instance)</option>
                                            </select>
                                        </div>

                                        {settings.ai_provider === 'openai' && (
                                            <div style={{ marginBottom: '16px' }}>
                                                <TextControl
                                                    label="OpenAI API Key"
                                                    value={settings.openai_key}
                                                    type="password"
                                                    onChange={(val) => setSettings({ ...settings, openai_key: val })}
                                                    placeholder="sk-..."
                                                />
                                            </div>
                                        )}

                                        {settings.ai_provider === 'anthropic' && (
                                            <div style={{ marginBottom: '16px' }}>
                                                <TextControl
                                                    label="Anthropic API Key"
                                                    value={settings.anthropic_key}
                                                    type="password"
                                                    onChange={(val) => setSettings({ ...settings, anthropic_key: val })}
                                                    placeholder="sk-ant-..."
                                                />
                                            </div>
                                        )}

                                        {settings.ai_provider === 'ollama' && (
                                            <div style={{ marginBottom: '16px' }}>
                                                <TextControl
                                                    label="Ollama Host URL"
                                                    value={settings.ollama_url}
                                                    onChange={(val) => setSettings({ ...settings, ollama_url: val })}
                                                    placeholder="http://localhost:11434"
                                                />
                                            </div>
                                        )}

                                        <div style={{ marginTop: '12px' }}>
                                            <Button isSecondary isBusy={isTestingAi} onClick={testAiConnection}>
                                                {isTestingAi ? 'Testing Connection...' : 'Test AI Connection'}
                                            </Button>
                                        </div>
                                    </div>

                                    {/* ── SECTION 5: Global Fallback Share Image ── */}
                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-semibold text-slate-800 mb-2">Global Fallback Share Image</h3>
                                        <p className="text-sm text-slate-500 mb-4">Specify a default social sharing image to be used when a post lacks custom featured media or local OG overrides.</p>
                                        
                                        <div style={{ display: 'flex', gap: '10px', alignItems: 'end' }}>
                                            <div style={{ flex: 1 }}>
                                                <TextControl
                                                    label="Fallback Image URL"
                                                    value={settings.default_share_image}
                                                    onChange={(val) => setSettings({ ...settings, default_share_image: val })}
                                                    placeholder="https://example.com/wp-content/uploads/default-og.jpg"
                                                />
                                            </div>
                                            {window.wp && window.wp.media && (
                                                <div style={{ marginBottom: '8px' }}>
                                                    <Button isSecondary onClick={openMediaLibrary}>Select Image</Button>
                                                </div>
                                            )}
                                        </div>
                                        {settings.default_share_image && (
                                            <div style={{ marginTop: '12px', maxWidth: '300px', border: '1px solid #cbd5e1', borderRadius: '6px', overflow: 'hidden', background: '#f8fafc', padding: '6px' }}>
                                                <img src={settings.default_share_image} alt="Fallback Share Preview" style={{ width: '100%', height: 'auto', display: 'block', borderRadius: '4px' }} />
                                            </div>
                                        )}
                                    </div>

                                    <div className="pt-4 border-t border-slate-200">
                                        <Button isPrimary isBusy={isSaving} onClick={saveSettings}>
                                            {isSaving ? 'Saving Settings...' : 'Save Settings'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {activeTab === 'technical' && (
                        <div className="space-y-6 max-w-4xl">
                            <div className="flex justify-between items-center border-b pb-4">
                                <h2 className="text-xl font-semibold m-0 text-slate-800">Technical SEO</h2>
                                <div className="flex bg-slate-100 p-1 rounded-lg border border-slate-200">
                                    <button
                                        onClick={() => setTechSubTab('robots')}
                                        className={`px-3 py-1.5 rounded-md font-medium text-xs transition-all ${techSubTab === 'robots' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        robots.txt
                                    </button>
                                    <button
                                        onClick={() => setTechSubTab('redirects')}
                                        className={`px-3 py-1.5 rounded-md font-medium text-xs transition-all ${techSubTab === 'redirects' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        Redirects Manager
                                    </button>
                                    <button
                                        onClick={() => setTechSubTab('404s')}
                                        className={`px-3 py-1.5 rounded-md font-medium text-xs transition-all ${techSubTab === '404s' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}
                                    >
                                        404 Error Monitor
                                    </button>
                                </div>
                            </div>

                            {/* robots.txt editor */}
                            {techSubTab === 'robots' && (
                                <div className="bg-slate-50 p-6 rounded-lg border border-slate-200">
                                    <h3 className="text-lg font-medium text-slate-800 mb-2">robots.txt Editor</h3>
                                    <p className="text-sm text-slate-500 mb-4">
                                        Control how search engine crawlers access your site. Changes here override the default WordPress robots.txt.
                                    </p>

                                    {robotsStatus && (
                                        <Notice status={robotsStatus.type} isDismissible={true} onRemove={() => setRobotsStatus(null)} className="mb-4">
                                            {robotsStatus.message}
                                        </Notice>
                                    )}

                                    {isLoadingRobots ? (
                                        <div className="p-8 text-center"><Spinner /></div>
                                    ) : (
                                        <>
                                            <textarea
                                                value={robotsTxt}
                                                onChange={(e) => setRobotsTxt(e.target.value)}
                                                rows={12}
                                                style={{
                                                    width: '100%', fontFamily: 'monospace', fontSize: '13px',
                                                    padding: '12px', border: '1px solid #cbd5e1', borderRadius: '6px',
                                                    background: '#fff', lineHeight: '1.6', resize: 'vertical'
                                                }}
                                            />
                                            <div className="mb-4 pt-4 border-t border-slate-200">
                                                <ToggleControl
                                                    label="AI Crawler Manager (Bot Blocker)"
                                                    help="Toggle-based controls to block specific AI crawlers (GPTBot, CCBot, Google-Extended, etc.) via robots.txt and HTTP headers."
                                                    checked={blockAiBots}
                                                    onChange={(checked) => setBlockAiBots(checked)}
                                                />
                                            </div>
                                            <div className="mt-4">
                                                <Button isPrimary isBusy={isSavingRobots} onClick={saveRobotsTxt}>
                                                    {isSavingRobots ? 'Saving...' : 'Save robots.txt'}
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            )}

                            {/* Redirects Manager */}
                            {techSubTab === 'redirects' && (
                                <div className="space-y-6">
                                    <div className="bg-slate-50 p-6 rounded-lg border border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Add New 301/302 Redirect</h3>
                                        <p className="text-sm text-slate-500 mb-4">
                                            Create absolute or relative redirects for old or broken URLs to maintain link juice and rank authority.
                                        </p>

                                        {redirectsStatus && (
                                            <Notice status={redirectsStatus.type} isDismissible={true} onRemove={() => setRedirectsStatus(null)} className="mb-4">
                                                {redirectsStatus.message}
                                            </Notice>
                                        )}

                                        <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                            <div className="md:col-span-4">
                                                <TextControl
                                                    label="Source URL path"
                                                    value={newRedirect.source}
                                                    onChange={(v) => setNewRedirect({ ...newRedirect, source: v })}
                                                    placeholder="e.g. /old-product-slug"
                                                />
                                            </div>
                                            <div className="md:col-span-4">
                                                <TextControl
                                                    label="Target URL path"
                                                    value={newRedirect.target}
                                                    onChange={(v) => setNewRedirect({ ...newRedirect, target: v })}
                                                    placeholder="e.g. /new-product-slug"
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="block text-xs font-semibold text-slate-600 mb-2">Redirect Code</label>
                                                <select
                                                    value={newRedirect.code}
                                                    onChange={(e) => setNewRedirect({ ...newRedirect, code: parseInt(e.target.value) })}
                                                    style={{ width: '100%', height: '30px', padding: '0 8px', border: '1px solid #cbd5e1', borderRadius: '4px' }}
                                                >
                                                    <option value={301}>301 Permanent</option>
                                                    <option value={302}>302 Temporary</option>
                                                    <option value={307}>307 Temporary</option>
                                                    <option value={410}>410 Gone</option>
                                                </select>
                                            </div>
                                            <div className="md:col-span-2 flex items-center h-10">
                                                <label className="flex items-center text-xs font-semibold text-slate-600 gap-1.5 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={newRedirect.is_regex}
                                                        onChange={(e) => setNewRedirect({ ...newRedirect, is_regex: e.target.checked })}
                                                    />
                                                    Regex Pattern
                                                </label>
                                            </div>
                                        </div>

                                        <div className="mt-4">
                                            <Button isPrimary onClick={addRedirect}>Add Redirect Rule</Button>
                                        </div>
                                    </div>

                                    {/* Active Redirect Rules */}
                                    <div className="bg-white p-6 rounded-lg border border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-4">Active Redirect Rules</h3>
                                        {isLoadingRedirects ? (
                                            <div className="p-8 text-center"><Spinner /></div>
                                        ) : redirects.length === 0 ? (
                                            <p className="text-sm text-slate-500 italic">No active redirect rules found.</p>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-slate-200">
                                                    <thead>
                                                        <tr>
                                                            <th className="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Source</th>
                                                            <th className="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Target</th>
                                                            <th className="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Type</th>
                                                            <th className="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Regex</th>
                                                            <th className="px-4 py-2 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100">
                                                        {redirects.map((rule) => (
                                                            <tr key={rule.id}>
                                                                <td className="px-4 py-3 text-xs font-mono text-slate-700 max-w-xs truncate" title={rule.source}>{rule.source}</td>
                                                                <td className="px-4 py-3 text-xs font-mono text-slate-700 max-w-xs truncate" title={rule.target}>{rule.target}</td>
                                                                <td className="px-4 py-3 text-xs text-slate-600 font-semibold">{rule.code}</td>
                                                                <td className="px-4 py-3 text-xs text-slate-500">{rule.is_regex ? 'Yes' : 'No'}</td>
                                                                <td className="px-4 py-3 text-right">
                                                                    <Button isDestructive isSmall onClick={() => deleteRedirect(rule.id)}>Delete</Button>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* 404 Error Log Monitor */}
                            {techSubTab === '404s' && (
                                <div className="bg-white p-6 rounded-lg border border-slate-200 space-y-4">
                                    <div className="flex justify-between items-center">
                                        <div>
                                            <h3 className="text-lg font-medium text-slate-800 m-0">Recent 404 Crawl Errors</h3>
                                            <p className="text-sm text-slate-500 m-0 mt-1">Real-time broken links detected by search engines or users. Cap limit 100.</p>
                                        </div>
                                        {logs404.length > 0 && (
                                            <Button isDestructive isSmall onClick={clearAll404Logs}>Clear All Logs</Button>
                                        )}
                                    </div>

                                    {isLoading404 ? (
                                        <div className="p-8 text-center"><Spinner /></div>
                                    ) : logs404.length === 0 ? (
                                        <div className="p-12 text-center text-slate-400 border border-dashed rounded-lg bg-slate-50">
                                            <span style={{ fontSize: '24px' }}>🛡️</span>
                                            <p className="m-0 mt-2 text-sm">Perfect! No 404 crawl errors currently logged.</p>
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-slate-200">
                                                <thead>
                                                    <tr>
                                                        <th className="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Broken URL</th>
                                                        <th className="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Hits</th>
                                                        <th className="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Last Hit</th>
                                                        <th className="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Referer</th>
                                                        <th className="px-4 py-2 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100">
                                                    {logs404.map((log) => (
                                                        <tr key={log.id}>
                                                            <td className="px-4 py-3 text-xs font-mono text-slate-800 font-semibold" title={log.uri}>{log.uri}</td>
                                                            <td className="px-4 py-3 text-xs text-slate-600">
                                                                <span className="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-100 font-semibold">{log.hits}</span>
                                                            </td>
                                                            <td className="px-4 py-3 text-xs text-slate-500">{log.last_hit}</td>
                                                            <td className="px-4 py-3 text-xs text-slate-500 max-w-xs truncate" title={log.referer || 'Direct Hit'}>{log.referer || 'Direct Hit'}</td>
                                                            <td className="px-4 py-3 text-right flex justify-end gap-2">
                                                                <Button isPrimary isSmall onClick={() => convert404ToRedirect(log.uri)}>Redirect</Button>
                                                                <Button isDestructive isSmall onClick={() => delete404Log(log.id)}>Dismiss</Button>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {activeTab === 'sitemaps' && (
                        <div className="space-y-6 max-w-3xl">
                            <h2 className="text-xl font-semibold m-0 text-slate-800">XML Sitemaps</h2>
                            <p className="text-slate-600">Dynamic sitemaps optimized for speed. Transients-cached for ultra-fast performance on high-traffic websites.</p>

                            {isLoadingSitemaps ? (
                                <div className="p-8 text-center"><Spinner /></div>
                            ) : (
                                <div className="bg-slate-50 p-6 rounded-lg border border-slate-200 space-y-6">
                                    {sitemapsStatus && (
                                        <Notice status={sitemapsStatus.type} isDismissible={true} onRemove={() => setSitemapsStatus(null)}>
                                            {sitemapsStatus.message}
                                        </Notice>
                                    )}

                                    <div className="space-y-4">
                                        <ToggleControl
                                            label="Enable Main XML Sitemap"
                                            help="Generates an index sitemap containing posts, pages, and images automatically."
                                            checked={sitemapsData.enable_index_sitemap}
                                            onChange={(val) => setSitemapsData({ ...sitemapsData, enable_index_sitemap: val })}
                                        />

                                        <ToggleControl
                                            label="Enable Google News XML Sitemap"
                                            help="Generates a news sitemap containing only posts published within the last 48 hours for immediate crawl indexing."
                                            checked={sitemapsData.enable_news_sitemap}
                                            onChange={(val) => setSitemapsData({ ...sitemapsData, enable_news_sitemap: val })}
                                        />

                                        <ToggleControl
                                            label="Enable Video XML Sitemap"
                                            help="Generates an XML sitemap of all video-enriched posts, automatically parsing embeds like YouTube and Vimeo."
                                            checked={sitemapsData.enable_video_sitemap}
                                            onChange={(val) => setSitemapsData({ ...sitemapsData, enable_video_sitemap: val })}
                                        />
                                    </div>

                                    {/* Exclusions */}
                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Exclude Content Types</h3>
                                        <p className="text-sm text-slate-500 mb-4">Uncheck post types you want to completely hide from crawlers inside the sitemaps.</p>
                                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                                            {sitemapsData.available_types && sitemapsData.available_types.map((type) => (
                                                <label key={type.name} className="flex items-center text-sm text-slate-700 gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={!sitemapsData.exclude_types.includes(type.name)}
                                                        onChange={(e) => {
                                                            const newExclude = e.target.checked
                                                                ? sitemapsData.exclude_types.filter(t => t !== type.name)
                                                                : [...sitemapsData.exclude_types, type.name];
                                                            setSitemapsData({ ...sitemapsData, exclude_types: newExclude });
                                                        }}
                                                    />
                                                    {type.label}
                                                </label>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Manual Exclusions */}
                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Exclude Specific Posts or Pages</h3>
                                        <p className="text-sm text-slate-500 mb-3">Provide a comma-separated list of Post IDs (e.g. 12, 104, 301) to manually exclude from all sitemaps.</p>
                                        <TextControl
                                            value={sitemapsData.exclude_posts}
                                            onChange={(val) => setSitemapsData({ ...sitemapsData, exclude_posts: val })}
                                            placeholder="e.g. 15, 340, 219"
                                        />
                                    </div>

                                    {/* Tuning Ranges */}
                                    <div className="pt-6 border-t border-slate-200 space-y-4">
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Sitemap Signal Tuning</h3>
                                        <p className="text-sm text-slate-500 mb-4">Tune Priority (0.0 to 1.0) and Change Frequency hints to guide search engine crawls.</p>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div className="space-y-4 bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
                                                <h4 className="font-semibold text-xs text-slate-400 uppercase tracking-wider">Posts Settings</h4>
                                                <div>
                                                    <label className="block text-xs font-semibold text-slate-600 mb-1">Post Priority: {sitemapsData.sitemap_priority_post}</label>
                                                    <input 
                                                        type="range" 
                                                        min="0.0" 
                                                        max="1.0" 
                                                        step="0.1" 
                                                        value={sitemapsData.sitemap_priority_post} 
                                                        onChange={(e) => setSitemapsData({ ...sitemapsData, sitemap_priority_post: e.target.value })} 
                                                        className="w-full accent-amber-500 cursor-pointer"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-semibold text-slate-600 mb-1">Post Change Frequency</label>
                                                    <select 
                                                        value={sitemapsData.sitemap_changefreq_post}
                                                        onChange={(e) => setSitemapsData({ ...sitemapsData, sitemap_changefreq_post: e.target.value })}
                                                        className="w-full p-2 border border-slate-300 rounded text-sm bg-white"
                                                    >
                                                        <option value="always">Always</option>
                                                        <option value="hourly">Hourly</option>
                                                        <option value="daily">Daily</option>
                                                        <option value="weekly">Weekly</option>
                                                        <option value="monthly">Monthly</option>
                                                        <option value="yearly">Yearly</option>
                                                        <option value="never">Never</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div className="space-y-4 bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
                                                <h4 className="font-semibold text-xs text-slate-400 uppercase tracking-wider">Pages Settings</h4>
                                                <div>
                                                    <label className="block text-xs font-semibold text-slate-600 mb-1">Page Priority: {sitemapsData.sitemap_priority_page}</label>
                                                    <input 
                                                        type="range" 
                                                        min="0.0" 
                                                        max="1.0" 
                                                        step="0.1" 
                                                        value={sitemapsData.sitemap_priority_page} 
                                                        onChange={(e) => setSitemapsData({ ...sitemapsData, sitemap_priority_page: e.target.value })} 
                                                        className="w-full accent-amber-500 cursor-pointer"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-semibold text-slate-600 mb-1">Page Change Frequency</label>
                                                    <select 
                                                        value={sitemapsData.sitemap_changefreq_page}
                                                        onChange={(e) => setSitemapsData({ ...sitemapsData, sitemap_changefreq_page: e.target.value })}
                                                        className="w-full p-2 border border-slate-300 rounded text-sm bg-white"
                                                    >
                                                        <option value="always">Always</option>
                                                        <option value="hourly">Hourly</option>
                                                        <option value="daily">Daily</option>
                                                        <option value="weekly">Weekly</option>
                                                        <option value="monthly">Monthly</option>
                                                        <option value="yearly">Yearly</option>
                                                        <option value="never">Never</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Info Cards containing live sitemap links */}
                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-3">Live XML Sitemaps</h3>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {sitemapsData.enable_index_sitemap && (
                                                <div className="bg-white p-4 rounded-lg border border-slate-200 shadow-sm flex items-center justify-between">
                                                    <div>
                                                        <div className="font-semibold text-xs text-slate-400 uppercase">Main Sitemap</div>
                                                        <div className="text-slate-800 font-medium text-sm mt-1 truncate">sitemap.xml</div>
                                                    </div>
                                                    <a href={`${window.location.origin}/sitemap.xml`} target="_blank" rel="noopener noreferrer" className="text-amber-500 hover:text-amber-700 text-xs font-semibold flex items-center">
                                                        Open sitemap ↗
                                                     </a>
                                                </div>
                                            )}
                                            {sitemapsData.enable_news_sitemap && (
                                                <div className="bg-white p-4 rounded-lg border border-slate-200 shadow-sm flex items-center justify-between">
                                                    <div>
                                                        <div className="font-semibold text-xs text-slate-400 uppercase">Google News Sitemap</div>
                                                        <div className="text-slate-800 font-medium text-sm mt-1 truncate">news-sitemap.xml</div>
                                                    </div>
                                                    <a href={`${window.location.origin}/news-sitemap.xml`} target="_blank" rel="noopener noreferrer" className="text-amber-500 hover:text-amber-700 text-xs font-semibold flex items-center">
                                                        Open sitemap ↗
                                                    </a>
                                                </div>
                                            )}
                                            {sitemapsData.enable_video_sitemap && (
                                                <div className="bg-white p-4 rounded-lg border border-slate-200 shadow-sm flex items-center justify-between">
                                                    <div>
                                                        <div className="font-semibold text-xs text-slate-400 uppercase">Video Sitemap</div>
                                                        <div className="text-slate-800 font-medium text-sm mt-1 truncate">video-sitemap.xml</div>
                                                    </div>
                                                    <a href={`${window.location.origin}/video-sitemap.xml`} target="_blank" rel="noopener noreferrer" className="text-amber-500 hover:text-amber-700 text-xs font-semibold flex items-center">
                                                        Open sitemap ↗
                                                    </a>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="pt-4">
                                        <Button isPrimary isBusy={isSavingSitemaps} onClick={saveSitemapsSettings}>
                                            {isSavingSitemaps ? 'Saving...' : 'Save Sitemap Settings'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {activeTab === 'schema' && (
                        <div className="space-y-6 max-w-3xl">
                            <h2 className="text-xl font-semibold m-0 text-slate-800">Schema Markup Templates</h2>
                            <p className="text-slate-600">
                                Build conditional rules to dynamically inject schemas like Article, Recipe, Event, and FAQPage across your site based on post types or categories.
                            </p>

                            {isLoadingSchemaRules ? (
                                <div className="p-8 text-center"><Spinner /></div>
                            ) : (
                                <div className="space-y-6">
                                    {schemaRulesStatus && (
                                        <Notice status={schemaRulesStatus.type} isDismissible={true} onRemove={() => setSchemaRulesStatus(null)}>
                                            {schemaRulesStatus.message}
                                        </Notice>
                                    )}

                                    {/* Active Display Rules */}
                                    <div className="bg-slate-50 p-6 rounded-lg border border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Active Display Rules</h3>
                                        {schemaRules.length === 0 ? (
                                            <p className="text-sm text-slate-500 italic">No global schema display rules configured yet. Standard schemas will output based on page types.</p>
                                        ) : (
                                            <div className="space-y-2 mt-4">
                                                {schemaRules.map((rule, idx) => (
                                                    <div key={idx} className="bg-white p-3 rounded border border-slate-200 shadow-sm flex items-center justify-between">
                                                        <div className="flex items-center gap-2 text-sm text-slate-700">
                                                            <span>On post type</span>
                                                            <strong className="text-amber-600 font-semibold bg-amber-50 px-2 py-0.5 rounded">{rule.post_type}</strong>
                                                            {rule.category && rule.category !== 'all' && (
                                                                <>
                                                                    <span>in category</span>
                                                                    <strong className="text-amber-600 font-semibold bg-amber-50 px-2 py-0.5 rounded">
                                                                        {availableCategories.find(c => String(c.id) === String(rule.category))?.name || rule.category}
                                                                    </strong>
                                                                </>
                                                            )}
                                                            <span>output</span>
                                                            <strong className="text-slate-800 font-semibold uppercase">{rule.schema_type}</strong>
                                                        </div>
                                                        <button 
                                                            onClick={() => {
                                                                const updated = schemaRules.filter((_, i) => i !== idx);
                                                                setSchemaRules(updated);
                                                            }}
                                                            className="text-xs font-semibold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-2.5 py-1 rounded transition-colors border border-transparent cursor-pointer"
                                                        >
                                                            Remove
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Add New Display Rule */}
                                    <div className="bg-slate-50 p-6 rounded-lg border border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-4">Create Global Display Rule</h3>
                                        <SchemaRuleForm 
                                            postTypes={availablePostTypes}
                                            categories={availableCategories}
                                            onAddRule={(newRule) => {
                                                setSchemaRules([...schemaRules, newRule]);
                                            }}
                                        />
                                    </div>

                                    <div className="pt-4 border-t border-slate-200">
                                        <Button isPrimary isBusy={isSavingSchemaRules} onClick={() => saveSchemaRules(schemaRules)}>
                                            {isSavingSchemaRules ? 'Saving...' : 'Save Schema Rules'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                    {activeTab === 'social' && (
                        <div className="space-y-6">
                            <div className="flex justify-between items-center mb-6">
                                <h2 className="text-xl font-semibold m-0 text-slate-800">Social Accounts & Auto-Sharing</h2>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium text-slate-600">Global Auto-Share</span>
                                    <label className="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" className="sr-only peer" checked={globalAutoShare} onChange={(e) => toggleGlobalAutoShare(e.target.checked)} />
                                        <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                    </label>
                                </div>
                            </div>
                            
                            <div className="bg-white border border-slate-200 rounded-lg shadow-sm">
                                <div className="p-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                                    <h3 className="font-semibold text-slate-800 m-0 text-sm">Connected Profiles</h3>
                                </div>
                                <div className="p-4">
                                    {isLoadingSocial ? (
                                        <div className="text-sm text-slate-500">Loading accounts...</div>
                                    ) : socialAccounts.length > 0 ? (
                                        <div className="space-y-3">
                                            {socialAccounts.map((acc) => (
                                                <div key={acc.id} className="border border-slate-200 rounded bg-white overflow-hidden">
                                                    <div className="flex justify-between items-center p-3">
                                                        <div className="flex items-center gap-3">
                                                            <div className="w-8 h-8 flex items-center justify-center rounded bg-slate-100 text-slate-600 capitalize font-bold text-xs">
                                                                {acc.network.charAt(0)}
                                                            </div>
                                                            <div>
                                                                <div className="font-semibold text-sm text-slate-800">{acc.profile_name}</div>
                                                                <div className="text-xs text-slate-500 capitalize">{acc.network}</div>
                                                            </div>
                                                        </div>
                                                        <div className="flex gap-2">
                                                            <button 
                                                                onClick={() => setEditingAccount(editingAccount?.id === acc.id ? null : acc)} 
                                                                className="text-xs text-blue-600 hover:text-blue-800 font-medium bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded transition-colors cursor-pointer border-none"
                                                            >
                                                                {editingAccount?.id === acc.id ? 'Cancel' : 'Edit Routing'}
                                                            </button>
                                                            <button onClick={() => deleteSocialAccount(acc.id)} className="text-xs text-red-600 hover:text-red-800 font-medium bg-red-50 hover:bg-red-100 px-3 py-1 rounded transition-colors cursor-pointer border-none">
                                                                Disconnect
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    {editingAccount?.id === acc.id && (
                                                        <div className="p-4 bg-slate-50 border-t border-slate-200 space-y-4">
                                                            <div>
                                                                <label className="block text-xs font-semibold text-slate-700 mb-1">Account / Page Name</label>
                                                                <input 
                                                                    type="text" 
                                                                    className="w-full p-2 border rounded text-sm"
                                                                    value={editingAccount.profile_name}
                                                                    onChange={(e) => setEditingAccount({...editingAccount, profile_name: e.target.value})}
                                                                />
                                                                <p className="text-xs text-slate-500 mt-1 mb-0">Helpful if you have multiple pages connected to the same platform.</p>
                                                            </div>
                                                            
                                                            <div>
                                                                <label className="block text-xs font-semibold text-slate-700 mb-1">Category Routing (Optional)</label>
                                                                <select 
                                                                    multiple 
                                                                    className="w-full p-2 border rounded text-sm"
                                                                    value={editingAccount.bound_categories || []}
                                                                    onChange={(e) => {
                                                                        const options = Array.from(e.target.selectedOptions, option => parseInt(option.value));
                                                                        setEditingAccount({...editingAccount, bound_categories: options});
                                                                    }}
                                                                    style={{ height: '100px' }}
                                                                >
                                                                    {socialCategories.map(cat => (
                                                                        <option key={cat.id} value={cat.id}>{cat.name}</option>
                                                                    ))}
                                                                </select>
                                                                <p className="text-xs text-slate-500 mt-1 mb-0">Select categories to bind. If selected, ONLY posts in these categories will be shared to this account. Leave empty to act as a catch-all.</p>
                                                            </div>

                                                            <div className="pt-2">
                                                                <Button 
                                                                    isPrimary 
                                                                    isBusy={isSavingAccount} 
                                                                    onClick={() => updateSocialAccount(acc.id, editingAccount)}
                                                                >
                                                                    Save Account Settings
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-sm text-slate-500 text-center py-6">No social accounts connected yet.</div>
                                    )}
                                </div>
                            </div>

                            <div className="bg-white border border-slate-200 rounded-lg shadow-sm">
                                <div className="p-4 border-b border-slate-200 bg-slate-50">
                                    <h3 className="font-semibold text-slate-800 m-0 text-sm">Platform API Settings</h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">Configure your Developer App ID and Secret for each platform to enable native OAuth connections.</p>
                                </div>
                                <div className="p-4 space-y-4">
                                    {['facebook', 'twitter', 'linkedin', 'pinterest'].map(net => (
                                        <div key={net} className="flex gap-4 items-end border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                                            <div className="w-32 font-semibold capitalize text-sm text-slate-700">{net}</div>
                                            <div className="flex-1">
                                                <label className="block text-xs font-semibold text-slate-600 mb-1">App ID / Client ID</label>
                                                <input 
                                                    type="text" 
                                                    className="w-full p-2 border rounded text-sm"
                                                    value={socialApps[net]?.app_id || ''}
                                                    onChange={(e) => setSocialApps({...socialApps, [net]: {...socialApps[net], app_id: e.target.value}})}
                                                />
                                            </div>
                                            <div className="flex-1">
                                                <label className="block text-xs font-semibold text-slate-600 mb-1">App Secret</label>
                                                <input 
                                                    type="password" 
                                                    className="w-full p-2 border rounded text-sm"
                                                    placeholder={socialApps[net]?.app_secret ? '********' : ''}
                                                    onChange={(e) => setSocialApps({...socialApps, [net]: {...socialApps[net], app_secret: e.target.value}})}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                    <div className="pt-2">
                                        <Button isPrimary isBusy={isSavingSocialApps} onClick={saveSocialSettings}>
                                            Save API Credentials
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="p-4 border border-slate-200 rounded-lg flex flex-col items-center text-center justify-center gap-3 bg-slate-50">
                                    <div className="font-semibold text-slate-800">Facebook</div>
                                    <p className="text-xs text-slate-500 m-0">Connect a Facebook Page to share updates automatically.</p>
                                    <button onClick={() => handleOAuthConnect('facebook')} className="bg-blue-600 text-white border-none rounded px-4 py-2 text-sm font-medium hover:bg-blue-700 cursor-pointer">Connect Facebook</button>
                                </div>
                                <div className="p-4 border border-slate-200 rounded-lg flex flex-col items-center text-center justify-center gap-3 bg-slate-50">
                                    <div className="font-semibold text-slate-800">X (Twitter)</div>
                                    <p className="text-xs text-slate-500 m-0">Connect an X account to auto-tweet published content.</p>
                                    <button onClick={() => handleOAuthConnect('twitter')} className="bg-slate-900 text-white border-none rounded px-4 py-2 text-sm font-medium hover:bg-black cursor-pointer">Connect X (Twitter)</button>
                                </div>
                                <div className="p-4 border border-slate-200 rounded-lg flex flex-col items-center text-center justify-center gap-3 bg-slate-50">
                                    <div className="font-semibold text-slate-800">LinkedIn</div>
                                    <p className="text-xs text-slate-500 m-0">Connect a LinkedIn Profile or Company Page.</p>
                                    <button onClick={() => handleOAuthConnect('linkedin')} className="bg-blue-700 text-white border-none rounded px-4 py-2 text-sm font-medium hover:bg-blue-800 cursor-pointer">Connect LinkedIn</button>
                                </div>
                                <div className="p-4 border border-slate-200 rounded-lg flex flex-col items-center text-center justify-center gap-3 bg-slate-50">
                                    <div className="font-semibold text-slate-800">Pinterest</div>
                                    <p className="text-xs text-slate-500 m-0">Connect to pin your latest content automatically.</p>
                                    <button onClick={() => handleOAuthConnect('pinterest')} className="bg-red-600 text-white border-none rounded px-4 py-2 text-sm font-medium hover:bg-red-700 cursor-pointer">Connect Pinterest</button>
                                </div>
                            </div>

                        </div>
                    )}
                    {activeTab === 'llms' && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-semibold m-0 text-slate-800">LLM Agent Config (llms.txt)</h2>
                            <p className="text-slate-600 mb-6">Manage how AI agents (ChatGPT, Perplexity, Claude) interact with and index your site's content via the `/llms.txt` standard.</p>
                            
                            {llmsStatus && <Notice status={llmsStatus.type} onRemove={() => setLlmsStatus(null)}>{llmsStatus.message}</Notice>}

                            <div className="bg-white p-6 border rounded shadow-sm space-y-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Site Description</label>
                                    <TextareaControl
                                        value={llmsData.description}
                                        onChange={(val) => setLlmsData({ ...llmsData, description: val })}
                                        help="A short summary of what your site is about."
                                        rows={2}
                                    />
                                </div>
                                
                                <div className="flex gap-4">
                                    <div className="flex-1">
                                        <ToggleControl
                                            label="Allow Search Indexing"
                                            help="Permit AI search bots to crawl and index your content for answers."
                                            checked={llmsData.allow_index}
                                            onChange={(val) => setLlmsData({ ...llmsData, allow_index: val })}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <ToggleControl
                                            label="Allow Model Training"
                                            help="Permit AI companies to use your content to train foundational models."
                                            checked={llmsData.allow_training}
                                            onChange={(val) => setLlmsData({ ...llmsData, allow_training: val })}
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Contact URL</label>
                                    <TextControl
                                        value={llmsData.contact_url}
                                        onChange={(val) => setLlmsData({ ...llmsData, contact_url: val })}
                                        placeholder="e.g. https://yoursite.com/contact or mailto:you@yoursite.com"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Additional Notes</label>
                                    <TextareaControl
                                        value={llmsData.extra_notes}
                                        onChange={(val) => setLlmsData({ ...llmsData, extra_notes: val })}
                                        help="Any extra rules, terms of service, or instructions for AI agents."
                                        rows={3}
                                    />
                                </div>

                                <Button isPrimary isBusy={isSavingLlms} disabled={isSavingLlms} onClick={saveLlmsSettings}>
                                    Save llms.txt Config
                                </Button>
                            </div>

                            {llmsData.preview && (
                                <div className="mt-8">
                                    <h3 className="text-lg font-semibold text-slate-800">File Preview</h3>
                                    <div className="bg-slate-900 text-slate-50 p-4 rounded text-sm overflow-auto max-h-96 whitespace-pre">
                                        {llmsData.preview}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {activeTab === 'bulk_meta' && (
                        <div className="space-y-6">
                            <div className="flex justify-between items-center">
                                <div>
                                    <h2 className="text-xl font-semibold m-0 text-slate-800">Bulk Meta Editor</h2>
                                    <p className="text-slate-600 mt-1 mb-0">Rapidly audit and update SEO Titles and Meta Descriptions.</p>
                                </div>
                                <Button 
                                    isPrimary 
                                    isBusy={isSavingBulkMeta} 
                                    disabled={Object.keys(bulkMetaEdits).length === 0 || isSavingBulkMeta}
                                    onClick={saveBulkMetaEdits}
                                >
                                    Save {Object.keys(bulkMetaEdits).length} Changes
                                </Button>
                            </div>

                            {bulkMetaStatus && <Notice status={bulkMetaStatus.type} onRemove={() => setBulkMetaStatus(null)}>{bulkMetaStatus.message}</Notice>}

                            <div className="flex gap-4 items-end bg-white p-4 border rounded shadow-sm">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Post Type</label>
                                    <select 
                                        value={bulkMetaParams.post_type} 
                                        onChange={(e) => setBulkMetaParams({ ...bulkMetaParams, post_type: e.target.value, page: 1 })}
                                        className="p-2 border rounded"
                                    >
                                        {bulkMeta.post_types.map(t => (
                                            <option key={t.name} value={t.name}>{t.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="flex-1">
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Search</label>
                                    <input 
                                        type="text" 
                                        value={bulkMetaParams.search}
                                        onChange={(e) => setBulkMetaParams({ ...bulkMetaParams, search: e.target.value })}
                                        onKeyDown={(e) => e.key === 'Enter' && fetchBulkMeta()}
                                        placeholder="Search titles..." 
                                        className="p-2 border rounded w-full" 
                                    />
                                </div>
                                <Button isSecondary onClick={() => fetchBulkMeta()}>Filter</Button>
                            </div>

                            {isLoadingBulkMeta ? (
                                <div className="text-center p-8"><Spinner /></div>
                            ) : (
                                <div className="bg-white border rounded shadow-sm overflow-hidden">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="bg-slate-50 border-b">
                                                <th className="p-3 text-sm font-semibold text-slate-700 w-1/4">Post / Page</th>
                                                <th className="p-3 text-sm font-semibold text-slate-700 w-1/3">SEO Title</th>
                                                <th className="p-3 text-sm font-semibold text-slate-700">Meta Description</th>
                                                <th className="p-3 text-sm font-semibold text-slate-700 w-24">NoIndex</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {bulkMeta.items.map(item => {
                                                const editedTitle = bulkMetaEdits[item.id]?.seo_title ?? item.seo_title;
                                                const editedDesc = bulkMetaEdits[item.id]?.seo_desc ?? item.seo_desc;
                                                const editedNoIndex = bulkMetaEdits[item.id]?.noindex ?? item.noindex;
                                                
                                                return (
                                                    <tr key={item.id} className="hover:bg-slate-50">
                                                        <td className="p-3 align-top">
                                                            <div className="font-semibold text-brand-600 text-sm mb-1">{item.title}</div>
                                                            <div className="text-xs text-slate-400">ID: {item.id} • {item.modified}</div>
                                                            <a href={item.url} target="_blank" rel="noreferrer" className="text-xs text-blue-500 hover:underline">View ›</a>
                                                        </td>
                                                        <td className="p-3 align-top">
                                                            <textarea 
                                                                value={editedTitle}
                                                                onChange={(e) => handleBulkMetaEdit(item.id, 'seo_title', e.target.value)}
                                                                className="w-full p-2 border rounded text-sm text-slate-700" 
                                                                rows="2" 
                                                                placeholder="Optimized Title..."
                                                            />
                                                            <div className={`text-xs mt-1 text-right ${editedTitle.length > 60 ? 'text-red-500' : 'text-slate-400'}`}>
                                                                {editedTitle.length} / 60
                                                            </div>
                                                        </td>
                                                        <td className="p-3 align-top">
                                                            <textarea 
                                                                value={editedDesc}
                                                                onChange={(e) => handleBulkMetaEdit(item.id, 'seo_desc', e.target.value)}
                                                                className="w-full p-2 border rounded text-sm text-slate-700" 
                                                                rows="3" 
                                                                placeholder="Meta description..."
                                                            />
                                                            <div className={`text-xs mt-1 text-right ${editedDesc.length > 160 ? 'text-red-500' : 'text-slate-400'}`}>
                                                                {editedDesc.length} / 160
                                                            </div>
                                                        </td>
                                                        <td className="p-3 align-top text-center">
                                                            <input 
                                                                type="checkbox" 
                                                                checked={editedNoIndex}
                                                                onChange={(e) => handleBulkMetaEdit(item.id, 'noindex', e.target.checked)}
                                                            />
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                            {bulkMeta.items.length === 0 && (
                                                <tr><td colSpan="4" className="text-center p-8 text-slate-500">No content found.</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                    
                                    {/* Pagination */}
                                    {bulkMeta.pages > 1 && (
                                        <div className="flex justify-between items-center p-4 border-t bg-slate-50">
                                            <Button 
                                                isSecondary 
                                                disabled={bulkMeta.page <= 1}
                                                onClick={() => setBulkMetaParams({ ...bulkMetaParams, page: bulkMeta.page - 1 })}
                                            >
                                                « Previous
                                            </Button>
                                            <span className="text-sm font-medium">Page {bulkMeta.page} of {bulkMeta.pages}</span>
                                            <Button 
                                                isSecondary 
                                                disabled={bulkMeta.page >= bulkMeta.pages}
                                                onClick={() => setBulkMetaParams({ ...bulkMetaParams, page: bulkMeta.page + 1 })}
                                            >
                                                Next »
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                </main>
            </div>
        </div>
        )}
        </>
    );
};

const SchemaRuleForm = ({ postTypes, categories, onAddRule }) => {
    const [postType, setPostType] = useState('post');
    const [category, setCategory] = useState('all');
    const [schemaType, setSchemaType] = useState('article');
    const [articleType, setArticleType] = useState('BlogPosting');

    return (
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label className="block text-xs font-semibold text-slate-600 mb-1">Post Type</label>
                <select 
                    value={postType} 
                    onChange={(e) => setPostType(e.target.value)} 
                    className="w-full p-2 border border-slate-300 rounded text-sm bg-white cursor-pointer"
                >
                    {postTypes.map(t => (
                        <option key={t.name} value={t.name}>{t.label}</option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-xs font-semibold text-slate-600 mb-1">Category Rule</label>
                <select 
                    value={category} 
                    onChange={(e) => setCategory(e.target.value)} 
                    disabled={postType !== 'post'}
                    className="w-full p-2 border border-slate-300 rounded text-sm bg-white disabled:bg-slate-100 disabled:text-slate-400 cursor-pointer"
                >
                    <option value="all">All Categories</option>
                    {categories.map(c => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-xs font-semibold text-slate-600 mb-1">Output Schema</label>
                <select 
                    value={schemaType} 
                    onChange={(e) => setSchemaType(e.target.value)} 
                    className="w-full p-2 border border-slate-300 rounded text-sm bg-white cursor-pointer"
                >
                    <option value="article">Article</option>
                    <option value="recipe">Recipe</option>
                    <option value="event">Event</option>
                    <option value="faq">FAQPage</option>
                    <option value="product">Product</option>
                    <option value="local">LocalBusiness</option>
                </select>
            </div>
            {schemaType === 'article' ? (
                <div>
                    <label className="block text-xs font-semibold text-slate-600 mb-1">Article Type</label>
                    <select 
                        value={articleType} 
                        onChange={(e) => setArticleType(e.target.value)} 
                        className="w-full p-2 border border-slate-300 rounded text-sm bg-white cursor-pointer"
                    >
                        <option value="BlogPosting">BlogPosting</option>
                        <option value="Article">Article</option>
                        <option value="NewsArticle">NewsArticle</option>
                    </select>
                </div>
            ) : (
                <div />
            )}
            <div>
                <button 
                    onClick={() => {
                        onAddRule({ 
                            post_type: postType, 
                            category: postType === 'post' ? category : 'all', 
                            schema_type: schemaType,
                            ...(schemaType === 'article' ? { article_type: articleType } : {})
                        });
                    }}
                    className="w-full p-2 bg-amber-500 hover:bg-amber-600 text-white rounded text-sm font-semibold transition-colors h-[38px] flex items-center justify-center cursor-pointer border border-transparent"
                >
                    Add Rule
                </button>
            </div>
        </div>
    );
};

const NavItem = ({ label, active, onClick }) => (
    <button 
        onClick={onClick}
        className={`text-left px-4 py-2 rounded font-medium transition-colors ${active ? 'bg-slate-100 text-brand-600' : 'text-slate-600 hover:bg-slate-50'}`}
    >
        {label}
    </button>
);

const StatCard = ({ title, value, type = 'default' }) => (
    <div className="p-4 border rounded-lg shadow-sm bg-white">
        <h3 className="text-sm font-medium text-slate-500 m-0">{title}</h3>
        <p className={`text-3xl font-bold mt-2 mb-0 ${type === 'success' ? 'text-green-600' : 'text-slate-900'}`}>{value}</p>
    </div>
);

export default App;
