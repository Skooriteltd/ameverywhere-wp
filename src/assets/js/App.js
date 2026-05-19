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
        auto_index: true
    });
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
                    auto_index: data.auto_index !== undefined ? data.auto_index : true
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
            .then(data => { setRobotsTxt(data.content || ''); setRobotsLoaded(true); })
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
                body: JSON.stringify({ content: robotsTxt })
            });
            setRobotsStatus(response.ok
                ? { type: 'success', message: 'robots.txt saved successfully!' }
                : { type: 'error', message: 'Failed to save robots.txt.' }
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
        exclude_types: [],
        exclude_posts: '',
        available_types: []
    });
    const [isLoadingSitemaps, setIsLoadingSitemaps] = useState(false);
    const [isSavingSitemaps, setIsSavingSitemaps] = useState(false);
    const [sitemapsStatus, setSitemapsStatus] = useState(null);

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

    // Auto-fetch data on active tab transitions
    useEffect(() => {
        if (activeTab === 'sitemaps') {
            fetchSitemapsSettings();
        }
        if (activeTab === 'technical') {
            if (techSubTab === 'redirects') {
                fetchRedirects();
            } else if (techSubTab === '404s') {
                fetch404Logs();
            }
        }
    }, [activeTab, techSubTab]);

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
                        <NavItem label="Instant Indexing (API)" active={activeTab === 'settings'} onClick={() => setActiveTab('settings')} />
                        <NavItem label="Technical SEO" active={activeTab === 'technical'} onClick={() => setActiveTab('technical')} />
                        <NavItem label="XML Sitemaps" active={activeTab === 'sitemaps'} onClick={() => setActiveTab('sitemaps')} />
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
                            <h2 className="text-xl font-semibold m-0 text-slate-800">Instant Indexing APIs</h2>
                            <p className="text-slate-600">Enter your API credentials below to automatically ping Google and Bing whenever you publish or update content.</p>
                            {isLoadingSettings ? (
                                <div className="p-8 text-center"><Spinner /></div>
                            ) : (
                                <div className="bg-slate-50 p-6 rounded-lg border border-slate-200 space-y-6">
                                    {saveStatus && (
                                        <Notice status={saveStatus.type} isDismissible={true} onRemove={() => setSaveStatus(null)}>
                                            {saveStatus.message}
                                        </Notice>
                                    )}
                                    <div>
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Google Indexing API</h3>
                                        <p className="text-sm text-slate-500 mb-4">Paste the entire contents of your Google Cloud Service Account JSON key file.</p>
                                        <TextareaControl value={settings.google_indexing_key} onChange={(val) => setSettings({ ...settings, google_indexing_key: val })} rows={8} placeholder='{"type": "service_account", ...}' />
                                    </div>
                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Bing IndexNow API Key</h3>
                                        <p className="text-sm text-slate-500 mb-4">Enter your unique 8-32 character IndexNow API key.</p>
                                        <TextControl value={settings.indexnow_key} onChange={(val) => setSettings({ ...settings, indexnow_key: val })} placeholder="e.g. 1a2b3c4d5e6f7g8h9i0j" />
                                    </div>
                                    <div className="pt-6 border-t border-slate-200">
                                        <ToggleControl label="Enable Automatic Indexing" help="When enabled, publishing or updating a post will automatically ping Google and Bing." checked={settings.auto_index} onChange={(val) => setSettings({ ...settings, auto_index: val })} />
                                    </div>
                                    <div className="pt-4">
                                        <Button isPrimary isBusy={isSaving} onClick={saveSettings}>
                                            {isSaving ? 'Saving...' : 'Save API Credentials'}
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
                                                    <a href={`${window.location.origin}/sitemap.xml`} target="_blank" rel="noopener noreferrer" className="text-brand-500 hover:text-brand-700 text-xs font-semibold flex items-center">
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
                                                    <a href={`${window.location.origin}/news-sitemap.xml`} target="_blank" rel="noopener noreferrer" className="text-brand-500 hover:text-brand-700 text-xs font-semibold flex items-center">
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
                </main>
            </div>
        </div>
        )}
        </>
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
