# TokenField Component

A React component that wraps the WordPress `FormTokenField` in a consistent Field layout pattern.

## Features

- **WordPress Integration**: Built on top of `@wordpress/components` FormTokenField
- **Consistent UI**: Follows the project's Field wrapper pattern for labels, errors, and styling
- **Accessibility**: Includes proper ARIA labels and keyboard navigation
- **Flexible**: Supports all FormTokenField props plus additional field-specific props

## Usage

### Basic Usage

```jsx
import TokenField from '../components/TokenField';

function MyComponent() {
  const [selectedTags, setSelectedTags] = useState(['javascript', 'react']);
  const availableTags = ['javascript', 'react', 'vue', 'angular', 'node'];

  return (
    <TokenField
      label="Tags"
      htmlFor="tags-field"
      value={selectedTags}
      onChange={setSelectedTags}
      suggestions={availableTags}
      placeholder="Add tags..."
      required
    />
  );
}
```

### Advanced Usage

```jsx
import TokenField from '../components/TokenField';

function EmailTokenField() {
  const [emails, setEmails] = useState(['john@example.com']);
  const [error, setError] = useState('');

  const validateEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const handleChange = (newEmails) => {
    const invalidEmails = newEmails.filter(email => !validateEmail(email));
    if (invalidEmails.length > 0) {
      setError(`Invalid email format: ${invalidEmails.join(', ')}`);
    } else {
      setError('');
      setEmails(newEmails);
    }
  };

  return (
    <TokenField
      label="Email Recipients"
      htmlFor="email-field"
      value={emails}
      onChange={handleChange}
      placeholder="Enter email addresses..."
      maxLength={10}
      tokenizeOnSpace={false}
      saveTransform={(value) => value.trim().toLowerCase()}
      error={error}
      required
    />
  );
}
```

## Props

### Field Wrapper Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | `string` | - | Field label text |
| `htmlFor` | `string` | - | ID of the input element for accessibility |
| `required` | `boolean` | `false` | Whether the field is required |
| `error` | `string` | - | Error message to display |
| `className` | `string` | - | Additional CSS classes |

### TokenField Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `value` | `Array<string\|object>` | `[]` | Current token values |
| `onChange` | `function` | - | Callback when tokens change (required) |
| `suggestions` | `Array<string>` | `[]` | Array of suggested token values |
| `placeholder` | `string` | `'Enter values...'` | Placeholder text |
| `maxLength` | `number` | - | Maximum number of tokens allowed |
| `disabled` | `boolean` | `false` | Whether the field is disabled |
| `displayTransform` | `function` | - | Transform function for displaying tokens |
| `saveTransform` | `function` | - | Transform function for saving tokens |
| `tokenizeOnSpace` | `boolean` | `true` | Whether to create tokens on space key |
| `maxSuggestions` | `number` | `100` | Maximum number of suggestions to display |
| `messages` | `object` | - | Custom messages for screen readers |

All other props are passed through to the underlying `FormTokenField` component.

## Field Component

The `TokenField` includes a reusable `Field` wrapper component that can be used with other input types. This follows the project's pattern for consistent form field styling and layout.

## Accessibility

- Proper ARIA labels and roles
- Keyboard navigation support
- Screen reader compatible
- High contrast mode support
- Focus management

## Browser Support

Supports all modern browsers. Uses CSS custom properties and modern CSS features with appropriate fallbacks.
