import { ToolDialogShell } from '../toolDialogHelpers';
import ToolDialogForm from '../../components/ToolDialogForm';

export const toolName = 'update_posts_find_replace';

export const policy = {
  concurrency: 'serial',
  canRerun: false,
};

export const Renderer = ({
  args,
  toolDialog,
  runTool,
  onCancel,
  policy: toolPolicy,
  isOpen,
}) => {
  const search = args.search ?? '';
  const replace = args.replace ?? '';
  const postStatus = args.post_status ?? 'any';
  const dryRun = args.dry_run !== undefined ? args.dry_run : true;
  const status = toolDialog.status || 'idle';
  const error = toolDialog.error || null;
  const result = toolDialog.result || null;
  const canRerun =
    status === 'done'
      ? Boolean(result?.dry_run)
      : Boolean(toolPolicy?.canRerun);
  const isPreviewResult = status === 'done' && result?.dry_run;
  const isError = status === 'error';
  const isBlocked = status === 'blocked';
  const total = result?.total ?? 0;
  const changed = Array.isArray(result?.changed) ? result.changed : [];

  const handleRun = (values) => {
    runTool(toolDialog.id, values);
  };

  const fields = [
    {
      name: 'search',
      label: 'Find',
      type: 'text',
      help: 'The text to search for.',
    },
    {
      name: 'replace',
      label: 'Replace',
      type: 'text',
      help: 'The text to replace with.',
    },
    {
      name: 'post_status',
      label: 'Post status',
      type: 'select',
      options: [
        { value: 'any', label: 'Any' },
        { value: 'publish', label: 'Published' },
        { value: 'draft', label: 'Draft' },
      ],
    },
    {
      name: 'dry_run',
      label: 'Dry run',
      type: 'hidden',
    },
  ];

  const title = status === 'cancelled'
    ? 'Update Posts - Find and Replace (Cancelled)'
    : 'Update Posts - Find and Replace';

  return (
    <ToolDialogShell
      status={status}
      title={title}
      canRerun={canRerun}
      policy={toolPolicy}
      isOpen={isOpen}
      error={error}
      showActions={false}
      onRun={(e) => {
        e.preventDefault();
        e.stopPropagation();
        handleRun({
          search,
          replace,
          post_status: postStatus,
          dry_run: false,
        });
      }}
      onCancel={(e) => {
        e.preventDefault();
        e.stopPropagation();
        onCancel(toolDialog.id);
      }}
    >
      {status === 'cancelled' ? null : isError ? (
        <div className="clawpress-tool-result-actions">
          <button
            className="button button-primary"
            type="button"
            onClick={() =>
              handleRun({
                ...(toolDialog.args || args),
                dry_run: toolDialog.args?.dry_run !== false,
              })
            }
          >
            Retry
          </button>
          <button className="button" type="button" onClick={() => onCancel(toolDialog.id)}>
            Cancel
          </button>
        </div>
      ) : status !== 'done' ? (
        <ToolDialogForm
          fields={fields}
          initialValues={{
            search,
            replace,
            post_status: postStatus,
            dry_run: true,
          }}
          disabled={status === 'running' || isBlocked}
          onSubmit={(values) =>
            handleRun({
              ...values,
              dry_run: true,
            })
          }
          onRun={(values) =>
            handleRun({
              ...values,
              dry_run: false,
            })
          }
          onCancel={() => onCancel(toolDialog.id)}
        />
      ) : (
        <div className="clawpress-tool-result">
          <div className="clawpress-tool-result-summary">
            <span>
              {isPreviewResult ? 'Preview ' : ''}
              Results
            </span>
          </div>
          {total === 0 ? (
            <p>No matches found.</p>
          ) : (
            <details className="clawpress-tool-result-list" open>
              <summary>Changed posts ({total})</summary>
              <ul>
                {changed.map((item) => (
                  <li key={item.id}>
                    <span className="clawpress-tool-result-title">
                      {item.title || 'Untitled'}
                    </span>
                    <span className="clawpress-tool-result-meta">ID {item.id}</span>
                    <span className="clawpress-tool-result-meta">
                      {item.count} change{item.count === 1 ? '' : 's'}
                    </span>
                  </li>
                ))}
              </ul>
            </details>
          )}
          {isPreviewResult ? (
            <div className="clawpress-tool-result-actions">
              <button
                className="button button-primary"
                type="button"
                onClick={() =>
                  handleRun({
                    search,
                    replace,
                    post_status: postStatus,
                    dry_run: false,
                  })
                }
              >
                Run
              </button>
              <button className="button" type="button" onClick={() => onCancel(toolDialog.id)}>
                Cancel
              </button>
            </div>
          ) : null}
        </div>
      )}
    </ToolDialogShell>
  );
};
