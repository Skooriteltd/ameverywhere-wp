import { useState, useEffect } from '@wordpress/element';

const CHART_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'];

const Ga4ChannelChart = ({ data }) => {
    const [Charts, setCharts] = useState(null);

    useEffect(() => {
        import('recharts').then(module => {
            setCharts(module);
        });
    }, []);

    if (!Charts) {
        return <div style={{ height: 250, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f8fafc', borderRadius: 8 }}>Loading chart...</div>;
    }

    const { ResponsiveContainer, PieChart, Pie, Cell, Tooltip } = Charts;

    return (
        <ResponsiveContainer width="100%" height={250}>
            <PieChart>
                <Pie data={data} dataKey="sessions" nameKey="channel" cx="50%" cy="50%" outerRadius={80} label={({ channel, percent }) => `${channel} ${(percent * 100).toFixed(0)}%`} labelLine={false}>
                    {data.map((_, i) => <Cell key={i} fill={CHART_COLORS[i % CHART_COLORS.length]} />)}
                </Pie>
                <Tooltip />
            </PieChart>
        </ResponsiveContainer>
    );
};

export default Ga4ChannelChart;
