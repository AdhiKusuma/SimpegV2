import SearchableCombobox from './SearchableCombobox';

export default function SelectCombobox({
    options = [],
    emptyLabel,
    emptyValue = '',
    ...props
}) {
    const optionValue = props.optionValue || ((option) => option.value ?? option);
    const optionLabel = props.optionLabel || ((option) => option.label ?? option);
    const emptyOption = { __selectComboboxEmpty: true, value: emptyValue, label: emptyLabel };
    const normalizedOptions = emptyLabel === undefined ? options : [emptyOption, ...options];

    return <SearchableCombobox
        {...props}
        options={normalizedOptions}
        optionValue={(option) => option?.__selectComboboxEmpty ? emptyValue : optionValue(option)}
        optionLabel={(option) => option?.__selectComboboxEmpty ? emptyLabel : optionLabel(option)}
        searchable={false}
    />;
}
