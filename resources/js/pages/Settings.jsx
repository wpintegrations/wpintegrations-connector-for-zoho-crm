import { useEffect, useState } from 'react'
import { Form, InputNumber, Button, Divider, App as AntApp } from 'antd'
import { CheckCircleFilled, ArrowRightOutlined } from '@ant-design/icons'
import { api } from '../api'
import BrandLoader from '../components/BrandLoader'
import ZohoCrmConnection from '../components/ZohoCrmConnection'

// What the separate Pro plugin offers, as the readme lists it.
const PRO_FEATURES = [
  'Any Zoho CRM module, custom modules included, with every field of the layout you choose',
  'Updates the existing record instead of creating a duplicate',
  'An Account and a Deal created with the contact, in the pipeline and stage you choose',
  'Record owner, assignment rules, tags, notes, and uploaded files in file fields or attached',
  'A delivery queue that retries a failed send on its own, and conditions such as sending only when consent is given'
]

export default function Settings({ meta, onChanged }) {
  const { message } = AntApp.useApp()
  const [form] = Form.useForm()
  const [saving, setSaving] = useState(false)
  // The page waits on the payload, so the form takes it as initial values:
  // setFieldsValue would land on an unmounted form and be dropped.
  const [initial, setInitial] = useState(null)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    api
      .get('/settings')
      .then(setInitial)
      .catch(e => message.error(e.message))
      .finally(() => setReady(true))
  }, [message])

  const save = async values => {
    setSaving(true)
    try {
      const saved = await api.post('/settings', values)
      form.setFieldsValue(saved)
      message.success('Settings saved.')
      onChanged?.()
    } catch (e) {
      message.error(e.message)
    } finally {
      setSaving(false)
    }
  }

  if (!ready) {
    return <BrandLoader />
  }

  return (
    <div style={{ maxWidth: 560 }}>
      <ZohoCrmConnection actionKey={meta.actions[0]?.key} onChanged={onChanged} />

      <Form form={form} layout="vertical" initialValues={initial || undefined} onFinish={save}>
        <Divider plain>Logs</Divider>
        <Form.Item name="log_retention_days" label="Keep delivery logs for (days)">
          <InputNumber min={1} max={365} />
        </Form.Item>

        <Button type="primary" htmlType="submit" loading={saving}>
          Save settings
        </Button>
      </Form>

      {meta.pro_url && (
        <div className="wpi-pro-card">
          <div className="wpi-pro-card__head">
            <span className="wpi-pro-card__badge">PRO</span>
            <span className="wpi-pro-card__title">Get more out of Zoho CRM</span>
          </div>
          <p className="wpi-pro-card__lead">
            Send every field your forms collect, keep one record per person, and turn submissions into
            accounts and deals — without losing a lead to a failed send.
          </p>
          <ul className="wpi-pro-card__list">
            {PRO_FEATURES.map(feature => (
              <li key={feature}>
                <CheckCircleFilled className="wpi-pro-card__check" />
                <span>{feature}</span>
              </li>
            ))}
          </ul>
          <div className="wpi-pro-card__foot">
            <a className="wpi-pro-card__cta" href={meta.pro_url} target="_blank" rel="noreferrer">
              Upgrade to Pro <ArrowRightOutlined />
            </a>
          </div>
        </div>
      )}
    </div>
  )
}
