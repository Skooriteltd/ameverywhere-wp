import { useState, useEffect, createElement } from '@wordpress/element';

let cachedRecharts = null;

export const useRecharts = () => {
    const [charts, setCharts] = useState(cachedRecharts);
    useEffect(() => {
        if (!cachedRecharts) {
            import('recharts').then(module => {
                cachedRecharts = module;
                setCharts(module);
            });
        }
    }, []);
    return charts;
};

export const LazyChart = ({ type, children, ...props }) => {
    const charts = useRecharts();
    if (!charts) return createElement('div', { style: { height: props.height || 250, display: 'flex', alignItems: 'center', justifyContent: 'center' } }, 'Loading chart...');
    const Component = charts[type];
    return createElement(Component, props, typeof children === 'function' ? children(charts) : children);
};
