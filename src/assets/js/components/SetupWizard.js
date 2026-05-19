import { useState, useEffect } from '@wordpress/element';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';

const SITE_TYPES = [
    { id: 'blog', label: 'Blog / Personal', icon: '✍️', desc: 'Content-focused site with regular posts' },
    { id: 'news', label: 'News / Media', icon: '📰', desc: 'High-frequency publishing with timely content' },
    { id: 'business', label: 'Business / Agency', icon: '🏢', desc: 'Company website with pages and services' },
    { id: 'ecommerce', label: 'eCommerce', icon: '🛒', desc: 'Online store with product pages' },
];

const SetupWizard = ({ onComplete }) => {
    const [step, setStep] = useState(0); // 0 = loading, 1 = configure, 2 = done
    const [autoData, setAutoData] = useState(null);
    const [siteType, setSiteType] = useState('blog');
    const [social, setSocial] = useState({ facebook: '', twitter: '', instagram: '', linkedin: '', youtube: '' });
    const [migrationResults, setMigrationResults] = useState({});
    const [isMigrating, setIsMigrating] = useState({});
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState(null);

    // Auto-detect on mount
    useEffect(() => {
        fetch(`${rankSavvyAdminConfig.apiUrl}/setup/auto-detect`, {
            headers: { 'X-WP-Nonce': rankSavvyAdminConfig.nonce }
        })
        .then(r => r.json())
        .then(data => {
            setAutoData(data);
            setSiteType(data.site_type || 'blog');
            setSocial(data.social || social);
            setStep(1);
        })
        .catch(() => {
            setStep(1); // Continue even if detection fails
        });
    }, []);

    const runMigration = async (pluginId, pluginName) => {
        setIsMigrating(prev => ({ ...prev, [pluginId]: true }));
        try {
            const response = await fetch(`${rankSavvyAdminConfig.apiUrl}/migration/run`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify({ plugin_id: pluginId })
            });
            const result = await response.json();
            setMigrationResults(prev => ({ ...prev, [pluginId]: result }));
        } catch (e) {
            setMigrationResults(prev => ({ ...prev, [pluginId]: { success: false, message: 'Migration failed.' } }));
        } finally {
            setIsMigrating(prev => ({ ...prev, [pluginId]: false }));
        }
    };

    const finishSetup = async () => {
        setIsSaving(true);
        try {
            await fetch(`${rankSavvyAdminConfig.apiUrl}/setup/complete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rankSavvyAdminConfig.nonce },
                body: JSON.stringify({ site_type: siteType, social })
            });
            setStep(2);
            setTimeout(() => onComplete(), 2000);
        } catch (e) {
            setError('Failed to save. Please try again.');
        } finally {
            setIsSaving(false);
        }
    };

    // ── Loading State ──
    if (step === 0) {
        return (
            <div style={styles.container}>
                <div style={styles.card}>
                    <div style={{ textAlign: 'center', padding: '48px 0' }}>
                        <Spinner />
                        <p style={{ marginTop: '16px', color: '#64748b' }}>Analyzing your site...</p>
                    </div>
                </div>
            </div>
        );
    }

    // ── Complete State ──
    if (step === 2) {
        return (
            <div style={styles.container}>
                <div style={styles.card}>
                    <div style={{ textAlign: 'center', padding: '48px 0' }}>
                        <div style={{ fontSize: '48px', marginBottom: '16px' }}>🎉</div>
                        <h2 style={{ margin: '0 0 8px', fontSize: '22px', color: '#0f172a' }}>You're all set!</h2>
                        <p style={{ margin: 0, color: '#64748b' }}>RankSavvy is fully configured and optimizing your site.</p>
                    </div>
                </div>
            </div>
        );
    }

    // ── Main Wizard (Single Page) ──
    const detectedPlugins = autoData?.detected_plugins || [];
    const hasSocialData = Object.values(social).some(v => v);

    return (
        <div style={styles.container}>
            <div style={styles.card}>
                {/* Header */}
                <div style={{ textAlign: 'center', marginBottom: '32px' }}>
                    <div style={{ fontSize: '36px', marginBottom: '8px' }}>🚀</div>
                    <h2 style={{ margin: '0 0 4px', fontSize: '22px', color: '#0f172a' }}>Welcome to RankSavvy</h2>
                    <p style={{ margin: 0, color: '#64748b', fontSize: '14px' }}>
                        Everything is already working. Just confirm a few details.
                    </p>
                </div>

                {error && (
                    <Notice status="error" isDismissible={true} onRemove={() => setError(null)}>
                        {error}
                    </Notice>
                )}

                {/* Section 1: Site Type */}
                <div style={styles.section}>
                    <h3 style={styles.sectionTitle}>What type of site is this?</h3>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                        {SITE_TYPES.map(type => (
                            <button
                                key={type.id}
                                onClick={() => setSiteType(type.id)}
                                style={{
                                    ...styles.typeButton,
                                    borderColor: siteType === type.id ? '#3b82f6' : '#e2e8f0',
                                    background: siteType === type.id ? '#eff6ff' : '#fff',
                                }}
                            >
                                <span style={{ fontSize: '20px' }}>{type.icon}</span>
                                <div>
                                    <div style={{ fontWeight: 600, fontSize: '13px', color: '#0f172a' }}>{type.label}</div>
                                    <div style={{ fontSize: '11px', color: '#64748b', marginTop: '2px' }}>{type.desc}</div>
                                </div>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Section 2: Migration (only if plugins detected) */}
                {detectedPlugins.length > 0 && (
                    <div style={styles.section}>
                        <h3 style={styles.sectionTitle}>Import existing SEO data</h3>
                        <p style={{ fontSize: '13px', color: '#64748b', margin: '0 0 12px' }}>
                            We detected SEO data from other plugins. Import with one click — your existing data won't be overwritten.
                        </p>
                        {detectedPlugins.map(plugin => (
                            <div key={plugin.id} style={styles.migrationRow}>
                                <div>
                                    <strong style={{ fontSize: '14px', color: '#0f172a' }}>{plugin.name}</strong>
                                    <span style={{ fontSize: '12px', color: '#64748b', marginLeft: '8px' }}>
                                        {plugin.posts} posts with data
                                    </span>
                                </div>
                                {migrationResults[plugin.id] ? (
                                    <span style={{
                                        fontSize: '12px', fontWeight: 500,
                                        color: migrationResults[plugin.id].success ? '#16a34a' : '#dc2626'
                                    }}>
                                        {migrationResults[plugin.id].success ? '✓ ' : '✗ '}
                                        {migrationResults[plugin.id].message}
                                    </span>
                                ) : (
                                    <Button
                                        isSecondary
                                        isSmall
                                        isBusy={isMigrating[plugin.id]}
                                        onClick={() => runMigration(plugin.id, plugin.name)}
                                    >
                                        {isMigrating[plugin.id] ? 'Importing...' : 'Import'}
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* Section 3: Social (collapsed by default, pre-filled) */}
                <details style={styles.section}>
                    <summary style={{ ...styles.sectionTitle, cursor: 'pointer', userSelect: 'none' }}>
                        Social Profiles {hasSocialData && <span style={{ fontSize: '12px', color: '#16a34a', fontWeight: 400 }}>(auto-detected)</span>}
                    </summary>
                    <div style={{ marginTop: '12px' }}>
                        <TextControl label="Facebook" value={social.facebook} onChange={v => setSocial({ ...social, facebook: v })} placeholder="https://facebook.com/yourpage" />
                        <TextControl label="Twitter / X" value={social.twitter} onChange={v => setSocial({ ...social, twitter: v })} placeholder="@yourhandle" />
                        <TextControl label="Instagram" value={social.instagram} onChange={v => setSocial({ ...social, instagram: v })} placeholder="https://instagram.com/yourpage" />
                        <TextControl label="LinkedIn" value={social.linkedin} onChange={v => setSocial({ ...social, linkedin: v })} placeholder="https://linkedin.com/company/yours" />
                        <TextControl label="YouTube" value={social.youtube} onChange={v => setSocial({ ...social, youtube: v })} placeholder="https://youtube.com/@yourchannel" />
                    </div>
                </details>

                {/* Finish Button */}
                <div style={{ marginTop: '24px', textAlign: 'center' }}>
                    <Button isPrimary isBusy={isSaving} onClick={finishSetup} style={{ padding: '8px 40px', fontSize: '14px' }}>
                        {isSaving ? 'Saving...' : 'Finish Setup'}
                    </Button>
                    <p style={{ fontSize: '12px', color: '#94a3b8', marginTop: '8px' }}>
                        You can change any of these later in Settings.
                    </p>
                </div>
            </div>
        </div>
    );
};

const styles = {
    container: {
        maxWidth: '640px',
        margin: '0 auto',
        padding: '20px 0',
    },
    card: {
        background: '#fff',
        border: '1px solid #e2e8f0',
        borderRadius: '12px',
        padding: '32px',
        boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
    },
    section: {
        marginTop: '24px',
        paddingTop: '24px',
        borderTop: '1px solid #f1f5f9',
    },
    sectionTitle: {
        fontSize: '15px',
        fontWeight: 600,
        color: '#0f172a',
        margin: '0 0 12px',
    },
    typeButton: {
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        padding: '12px 14px',
        border: '2px solid',
        borderRadius: '8px',
        cursor: 'pointer',
        textAlign: 'left',
        transition: 'all 0.15s',
    },
    migrationRow: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '10px 14px',
        background: '#f8fafc',
        borderRadius: '6px',
        marginBottom: '8px',
        border: '1px solid #e2e8f0',
    },
};

export default SetupWizard;
