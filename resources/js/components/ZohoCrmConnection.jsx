import { useEffect, useState } from 'react'
import { Alert, Button, Card, Input, Space, Typography, Form, App as AntApp, Tooltip } from 'antd'
import {
  LinkOutlined,
  CopyOutlined,
  DisconnectOutlined,
  CheckCircleFilled,
  ApiOutlined,
  ReloadOutlined
} from '@ant-design/icons'
import { api } from '../api'
import { formatDate } from '../format'
import { copyText } from '../copy-text'

/**
 * The site's Zoho CRM connection. Each site registers its own client in the
 * Zoho API Console, so no credentials ship with the plugin.
 */
export default function ZohoCrmConnection({ actionKey, onChanged }) {
  const { message, modal } = AntApp.useApp()
  const [status, setStatus] = useState(null)
  const [saving, setSaving] = useState(false)
  const [connecting, setConnecting] = useState(false)
  const [testing, setTesting] = useState(false)
  const [form] = Form.useForm()

  const load = () =>
    api
      .get('/oauth/status')
      .then(s => {
        setStatus(s)
        form.setFieldsValue({
          client_id: s.client_id || '',
          // Stars and the secret's last characters; sent back unchanged, it
          // means "keep the stored secret".
          client_secret: s.secret_mask || ''
        })
      })
      .catch(e => message.error(e.message))

  useEffect(() => {
    load()

    // The callback sends the browser back here carrying the outcome.
    const params = new URLSearchParams(window.location.search)
    const result = params.get('wpi_zcrm_oauth')
    if (result === 'connected') {
      message.success('Connected to Zoho CRM.')
      onChanged?.()
    } else if (result === 'error') {
      message.error(params.get('wpi_zcrm_oauth_error') || 'Connection failed.')
    }
    if (result) {
      params.delete('wpi_zcrm_oauth')
      params.delete('wpi_zcrm_oauth_error')
      window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`)
    }
  }, [])

  const copy = async () => {
    if (await copyText(status.redirect_uri)) {
      message.success('Redirect URI copied.')
    } else {
      message.warning('Copy failed — select the URL and copy it manually.')
    }
  }

  const saveCredentials = async () => {
    let values
    try {
      values = await form.validateFields()
    } catch {
      return
    }
    setSaving(true)
    try {
      const saved = await api.post('/oauth/credentials', values)
      setStatus(saved)
      form.setFieldsValue({ client_secret: saved.secret_mask || '' })
      message.success('Client credentials saved.')
    } catch (e) {
      message.error(e.message)
    } finally {
      setSaving(false)
    }
  }

  const connect = async () => {
    setConnecting(true)
    try {
      const { url } = await api.post('/oauth/authorize', {})
      window.location.href = url
    } catch (e) {
      message.error(e.message)
      setConnecting(false)
    }
  }

  // Re-runs consent with the stored credentials. Tokens are replaced only on
  // success, so a cancelled reconnect leaves the connection working.
  const reconnect = () =>
    modal.confirm({
      title: 'Reconnect to Zoho CRM?',
      content:
        'You will be sent to Zoho to allow access again — useful after revoking it there, or to switch to a different Zoho account. The current connection keeps working until the new one succeeds.',
      okText: 'Reconnect',
      onOk: connect
    })

  // Proves the token can still deliver; "connected" alone does not, since it
  // may have been revoked in Zoho since.
  const test = async () => {
    setTesting(true)
    try {
      const data = await api.post('/connection/test', { action_key: actionKey })
      message.success(data?.message || 'Connection successful.')
    } catch (e) {
      message.error(e.message)
    } finally {
      setTesting(false)
    }
  }

  const disconnect = () =>
    modal.confirm({
      title: 'Disconnect from Zoho CRM?',
      content: 'Your integrations will stop delivering until you reconnect.',
      okText: 'Disconnect',
      okButtonProps: { danger: true },
      onOk: async () => {
        try {
          setStatus(await api.post('/oauth/disconnect', {}))
          message.success('Disconnected.')
          onChanged?.()
        } catch (e) {
          message.error(e.message)
        }
      }
    })

  if (!status) {
    return <Card size="small" loading style={{ marginBottom: 24 }} />
  }

  return (
    <Card size="small" title="Zoho CRM connection" style={{ marginBottom: 24 }}>
      {status.connected ? (
        <Space direction="vertical" style={{ width: '100%' }}>
          <Alert
            type="success"
            showIcon
            icon={<CheckCircleFilled />}
            message="Connected to Zoho CRM"
            description={
              <>
                Your integrations are ready — form submissions are delivered to this Zoho CRM account
                {status.api_domain ? ` (${status.api_domain.replace('https://', '')})` : ''}.
                {status.connected_at ? ` Authorized ${formatDate(status.connected_at)}.` : ''}
              </>
            }
          />
          <Space wrap>
            <Button icon={<ApiOutlined />} loading={testing} onClick={test}>
              Test connection
            </Button>
            <Button icon={<ReloadOutlined />} loading={connecting} onClick={reconnect}>
              Reconnect
            </Button>
            <Button icon={<DisconnectOutlined />} danger onClick={disconnect}>
              Disconnect
            </Button>
          </Space>
        </Space>
      ) : (
        <>
          <Typography.Paragraph type="secondary" style={{ fontSize: 12 }}>
            Integrations deliver through this connection. In the{' '}
            <Typography.Link href="https://api-console.zoho.com/" target="_blank">
              Zoho API Console
            </Typography.Link>
            , add a <strong>Server-based Application</strong> client with your site as the homepage and
            the redirect URI below, then paste its Client ID and Client Secret here. If your Zoho account
            is outside the US data centre (EU, India, Australia, Japan, Canada…), turn on{' '}
            <strong>Multi-DC</strong> in the client's Settings tab.
          </Typography.Paragraph>

          <Form.Item label="Authorized redirect URI" style={{ marginBottom: 12 }}>
            <Space.Compact style={{ width: '100%' }}>
              <Input readOnly value={status.redirect_uri} onFocus={e => e.target.select()} />
              <Tooltip title="Copy">
                <Button icon={<CopyOutlined />} onClick={copy} />
              </Tooltip>
            </Space.Compact>
          </Form.Item>

          <Form form={form} layout="vertical">
            <Form.Item
              name="client_id"
              label="Client ID"
              rules={[{ required: true, message: 'Paste the Client ID from your Zoho client.' }]}
            >
              <Input autoComplete="off" spellCheck={false} placeholder="1000.XXXXXXXXXXXXXXXXXXXX" />
            </Form.Item>
            <Form.Item
              name="client_secret"
              label="Client Secret"
              extra="Stored encrypted; only its last characters are shown."
              rules={[{ required: true, message: 'Paste the Client Secret from your Zoho client.' }]}
            >
              <Input autoComplete="off" spellCheck={false} placeholder="Paste the Client Secret" />
            </Form.Item>
          </Form>

          <Space>
            <Button loading={saving} onClick={saveCredentials}>
              Save credentials
            </Button>
            <Tooltip title={status.has_credentials ? '' : 'Save your Client ID and Secret first.'}>
              <span style={{ display: 'inline-block' }}>
                <Button
                  type="primary"
                  icon={<LinkOutlined />}
                  loading={connecting}
                  disabled={!status.has_credentials}
                  onClick={connect}
                >
                  Connect to Zoho CRM
                </Button>
              </span>
            </Tooltip>
          </Space>
        </>
      )}
    </Card>
  )
}
