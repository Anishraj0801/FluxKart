// app/Http/Controllers/IdentifyController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdentifyController extends Controller
{
    public function identifyContact(Request $request)
    {
        $data = $request->json()->all();
        $email = $data['email'];
        $phoneNumber = $data['phoneNumber'];

        $existingContacts = DB::table('contacts')
            ->where('email', $email)
            ->orWhere('phoneNumber', $phoneNumber)
            ->get();

        if ($existingContacts->isEmpty()) {
            $newContactId = $this->createContact($email, $phoneNumber, null, 'primary');            // If no existing contacts, create a new one with linkPrecedence="primary"

            $result = $this->consolidateContacts($newContactId);
        } else {
            $primaryContactId = $existingContacts->first()->id;

            if ($existingContacts->first()->linkPrecedence == 'secondary') {
                // If the existing contact is "secondary," update it to "primary"
                DB::table('contacts')
                    ->where('id', $primaryContactId)
                    ->update(['linkPrecedence' => 'primary']);
            }

            // Create a new contact with linkPrecedence="secondary"
            $newContactId = $this->createContact($email, $phoneNumber, $primaryContactId, 'secondary');

            // Consolidate and return the result
            $result = $this->consolidateContacts($primaryContactId);
        }

        return response()->json(['contact' => $result]);
    }

    private function createContact($email, $phoneNumber, $linkedId, $precedence)
    {
        $newContactId = DB::table('contacts')->insertGetId([
            'email' => $email,
            'phoneNumber' => $phoneNumber,
            'linkedId' => $linkedId,
            'linkPrecedence' => $precedence,
            'createdAt' => now(),
            'updatedAt' => now(),
            'deletedAt' => null,
        ]);

        return $newContactId;
    }

    private function consolidateContacts($primaryContactId)
    {
        $primaryContact = DB::table('contacts')->find($primaryContactId);

        if (!$primaryContact || $primaryContact->linkPrecedence != 'primary') {
            return null;
        }

        $emails = [$primaryContact->email];
        $phoneNumbers = [$primaryContact->phoneNumber];
        $secondaryContactIds = [];

        $secondaryContacts = DB::table('contacts')
            ->where('linkedId', $primaryContactId)
            ->get();

        foreach ($secondaryContacts as $contact) {
            $emails[] = $contact->email;
            $phoneNumbers[] = $contact->phoneNumber;
            $secondaryContactIds[] = $contact->id;
        }

        return [
            'primaryContactId' => $primaryContactId,
            'emails' => $emails,
            'phoneNumbers' => $phoneNumbers,
            'secondaryContactIds' => $secondaryContactIds,
        ];
    }
}
