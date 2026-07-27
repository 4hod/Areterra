export default function ChartCard({ title, value, values, color }: { title: string; value: string | number; values: number[]; color: string }) {
    const w=100,h=40,max=Math.max(...values,1),min=Math.min(...values,0),range=max-min||1,step=w/(values.length-1||1);
    const points=values.map((v,i)=>`${i*step},${h-((v-min)/range)*h}`).join(' ');
    return <div className="hub-chart-card" style={{'--chart-accent':color} as React.CSSProperties}>
        <div className="hub-chart-top"><span>{title}</span><strong>{value}</strong></div>
        <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" aria-hidden><defs><linearGradient id={`fill-${title.replace(/\W/g,'')}`} x1="0" y1="0" x2="0" y2="1"><stop offset="0" stopColor={color} stopOpacity=".28"/><stop offset="1" stopColor={color} stopOpacity="0"/></linearGradient></defs><polygon points={`0,40 ${points} 100,40`} fill={`url(#fill-${title.replace(/\W/g,'')})`}/><polyline points={points} fill="none" stroke={color} strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/></svg>
    </div>;
}
