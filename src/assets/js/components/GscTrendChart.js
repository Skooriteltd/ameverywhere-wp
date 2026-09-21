import { useState, useEffect } from '@wordpress/element';

const GscTrendChart = ({ data }) => {
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
                <XAxis dataKey="keys.0" tick={{fontSize: 12}} />
                <YAxis yAxisId="left" tick={{fontSize: 12}} />
                <YAxis yAxisId="right" orientation="right" tick={{fontSize: 12}} />
                <Tooltip />
                <Line yAxisId="left" type="monotone" dataKey="clicks" stroke="#3b82f6" strokeWidth={2} name="Clicks" />
                <Line yAxisId="right" type="monotone" dataKey="impressions" stroke="#8b5cf6" strokeWidth={2} name="Impressions" />
            </LineChart>
        </ResponsiveContainer>
    );
};

export default GscTrendChart;
