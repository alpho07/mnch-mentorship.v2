<?php

namespace App\Http\Controllers;

use App\Models\MentorshipCoMentor;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CoMentorController extends Controller
{
    /**
     * Show invitation page.
     * Pending invitations show the accept/decline form.
     * Accepted/declined invitations show a friendly status message.
     */
    public function show(string $token)
    {
        $invitation = MentorshipCoMentor::where('invitation_token', $token)
            ->with(['user', 'inviter'])
            ->firstOrFail();

        $training = Training::findOrFail($invitation->training_id);

        // Revoked invitations cannot be used
        if ($invitation->status === 'revoked') {
            return view('co-mentor.invitation', [
                'invitation' => $invitation,
                'training' => $training,
                'inviter' => $invitation->inviter,
                'token' => $token,
                'error' => 'This invitation has been revoked by the lead mentor.',
                'success' => null,
            ]);
        }

        // Already responded invitations show a friendly confirmation
        if ($invitation->status === 'accepted') {
            return view('co-mentor.invitation', [
                'invitation' => $invitation,
                'training' => $training,
                'inviter' => $invitation->inviter,
                'token' => $token,
                'error' => null,
                'success' => 'You have already accepted this co-mentor invitation.',
            ]);
        }

        if ($invitation->status !== 'pending') {
            return view('co-mentor.invitation', [
                'invitation' => $invitation,
                'training' => $training,
                'inviter' => $invitation->inviter,
                'token' => $token,
                'error' => 'This invitation has already been '.$invitation->status.'.',
                'success' => null,
            ]);
        }

        return view('co-mentor.invitation', [
            'invitation' => $invitation,
            'training' => $training,
            'inviter' => $invitation->inviter,
            'token' => $token,
            'error' => null,
            'success' => null,
        ]);
    }

    /**
     * Process invitation acceptance or decline.
     */
    public function process(Request $request, string $token)
    {
        $request->validate([
            'action' => 'required|in:accept,decline',
        ]);

        $invitation = MentorshipCoMentor::where('invitation_token', $token)
            ->where('status', 'pending') // Only pending can be processed
            ->firstOrFail();

        // Ensure authenticated user matches invitation
        if (Auth::id() !== $invitation->user_id) {
            abort(403, 'This invitation is not for you.');
        }

        DB::beginTransaction();
        try {
            if ($request->action === 'accept') {
                $invitation->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);

                DB::commit();

                return redirect()
                    ->route('filament.admin.home')
                    ->with('success', 'You are now a co-mentor for this training!');
            } else {
                $invitation->update([
                    'status' => 'declined',
                ]);

                DB::commit();

                return redirect()
                    ->route('filament.admin.home')
                    ->with('info', 'Invitation declined.');
            }
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to process invitation: '.$e->getMessage()]);
        }
    }
}
