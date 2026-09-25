import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card } from '@/Components/Platform/ui';

interface Company {
    id: string;
    name: string;
}

interface ComingSoonPageProps {
    company: Company;
    title: string;
    body: string;
    [key: string]: unknown;
}

export default function ComingSoon({ company, title, body }: ComingSoonPageProps) {
    return (
        <PlatformLayout title={title} company={company} maxWidth="max-w-2xl">
            <Card>
                <div className="mb-3">
                    <Badge tone="warning">Not built yet</Badge>
                </div>
                <p className="text-sm text-slate-600 leading-relaxed">{body}</p>
                <p className="mt-4 text-xs text-slate-400">This step is optional — it never blocks Review or Activate.</p>
            </Card>
        </PlatformLayout>
    );
}
