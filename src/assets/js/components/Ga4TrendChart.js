import { useState, useEffect } from '@wordpress/element';

const Ga4TrendChart = ({ data }) => {
    const [Charts, setCharts] = useState(null);

    useEffect(() => {
        import('recharts').then(module => {
            setCharts(module);
        });
    }, []);

    if (!Charts) {
        return <div style={{ height: 250, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f8fafc', borderRadius: 8 }}>Loading chart...</div>;
    }

    const { ResponsiveContainer, LineChart, XAxis, YAxis, Tooltip, Line } = Charts;

    return (
        <ResponsiveContainer width="100%" height={250}>
            <LineChart data={data} margin={{ top: 4, right: 8, left: -20, bottom: 0 }}>
                <XAxis dataKey="date" tick={{fontSize: 12}} />
                <YAxis tick={{fontSize: 12}} />
                <Tooltip />
                <Line type="monotone" dataKey="sessions" stroke="#3b82f6" strokeWidth={2} name="Sessions" />
                <Line type="monotone" dataKey="screenPageViews" stroke="#10b981" strokeWidth={2} name="Pageviews" />
            </LineChart>
        </ResponsiveContainer>
    );
};

export default Ga4TrendChart;
