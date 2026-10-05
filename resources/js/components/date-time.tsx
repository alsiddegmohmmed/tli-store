const formatter = new Intl.DateTimeFormat('ar-SA', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'Asia/Riyadh',
    calendar: 'gregory',
    numberingSystem: 'latn',
});
export default function DateTime({ value }: { value: string }) {
    return <time dateTime={value}>{formatter.format(new Date(value))}</time>;
}
