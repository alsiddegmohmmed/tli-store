const formatter = new Intl.NumberFormat('ar-SA', {
    style: 'currency',
    currency: 'SAR',
    numberingSystem: 'latn',
});

export default function Money({ amount }: { amount: string }) {
    // Server amounts have two decimal places; preserve them without float conversion.
    const [whole, fraction = '00'] = amount.split('.');
    const integer =
        amount.startsWith('-') && BigInt(whole) === 0n ? -0 : BigInt(whole);
    const formatted = formatter
        .formatToParts(integer)
        .map((part) => (part.type === 'fraction' ? fraction : part.value))
        .join('');
    return (
        <bdi dir="rtl" className="whitespace-nowrap tabular-nums">
            {formatted}
        </bdi>
    );
}
