import { useState, useEffect } from '@wordpress/element';
import { Button, TextControl, TextareaControl, Notice, Spinner, ToggleControl } from '@wordpress/components';

const App = () => {
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

    return (
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
                        <NavItem 
                            label="Dashboard & GSC" 
                            active={activeTab === 'dashboard'} 
                            onClick={() => setActiveTab('dashboard')} 
                        />
                        <NavItem 
                            label="Instant Indexing (API)" 
                            active={activeTab === 'settings'} 
                            onClick={() => setActiveTab('settings')} 
                        />
                        <NavItem 
                            label="Technical SEO" 
                            active={activeTab === 'technical'} 
                            onClick={() => setActiveTab('technical')} 
                        />
                        <NavItem 
                            label="XML Sitemaps" 
                            active={activeTab === 'sitemaps'} 
                            onClick={() => setActiveTab('sitemaps')} 
                        />
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
                                        <StatCard title="Total Clicks" value={gscData.totalClicks} trend="+12%" type="success" />
                                        <StatCard title="Impressions" value={gscData.totalImpressions} trend="+5%" />
                                        <StatCard title="Avg. CTR" value={gscData.avgCtr} trend="stable" />
                                        <StatCard title="Avg. Position" value={gscData.avgPosition} trend="-1.2" type="success" />
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
                            <p className="text-slate-600">
                                Enter your API credentials below to automatically ping Google and Bing whenever you publish or update content.
                            </p>

                            {isLoadingSettings ? (
                                <div className="p-8 text-center"><Spinner /></div>
                            ) : (
                                <div className="bg-slate-50 p-6 rounded-lg border border-slate-200 space-y-6">
                                    {saveStatus && (
                                        <Notice 
                                            status={saveStatus.type} 
                                            isDismissible={true} 
                                            onRemove={() => setSaveStatus(null)}
                                        >
                                            {saveStatus.message}
                                        </Notice>
                                    )}

                                    <div>
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Google Indexing API</h3>
                                        <p className="text-sm text-slate-500 mb-4">
                                            Paste the entire contents of your Google Cloud Service Account JSON key file.
                                        </p>
                                        <TextareaControl
                                            value={settings.google_indexing_key}
                                            onChange={(val) => setSettings({ ...settings, google_indexing_key: val })}
                                            rows={8}
                                            placeholder='{"type": "service_account", ...}'
                                        />
                                    </div>

                                    <div className="pt-6 border-t border-slate-200">
                                        <h3 className="text-lg font-medium text-slate-800 mb-2">Bing IndexNow API Key</h3>
                                        <p className="text-sm text-slate-500 mb-4">
                                            Enter your unique 8-32 character IndexNow API key. We will automatically generate the validation text file for you.
                                        </p>
                                        <TextControl
                                            value={settings.indexnow_key}
                                            onChange={(val) => setSettings({ ...settings, indexnow_key: val })}
                                            placeholder="e.g. 1a2b3c4d5e6f7g8h9i0j"
                                        />
                                    </div>
                                    
                                    <div className="pt-6 border-t border-slate-200">
                                        <ToggleControl
                                            label="Enable Automatic Indexing"
                                            help="When enabled, publishing or updating a post will automatically ping Google and Bing."
                                            checked={settings.auto_index}
                                            onChange={(val) => setSettings({ ...settings, auto_index: val })}
                                        />
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

                    {['technical', 'sitemaps'].includes(activeTab) && (
                        <div className="p-8 text-center text-slate-500 bg-slate-50 rounded border border-dashed border-slate-200">
                            {activeTab} module coming soon in Sprint 2.
                        </div>
                    )}
                </main>
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
